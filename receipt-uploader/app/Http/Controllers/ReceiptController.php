<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Google\Client;
use Google\Service\Drive;

class ReceiptController extends Controller
{
    public function store(Request $request)
    {
        // Log the initial request for debugging
        Log::info('Receipt upload request started', [
            'user' => auth()->user(),
            'headers' => $request->header(),
            'files' => $request->files->all(),
            'request_data' => $request->all(),
        ]);

        // Validate only JPG and PNG files
        try {
            $request->validate(['receipt' => 'required|file|mimes:jpg,png,jpeg']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid file type. Please upload a JPG or PNG file.'], 400);
        }

        $file = $request->file('receipt');

        $originalFilename = $file->getClientOriginalName();

        // Explicitly store the file and check the result using Storage facade
        $path = $file->store('temp', 'local'); // Store in storage/app/temp/
        Log::info('Attempted to store file', ['file' => $file->getClientOriginalName(), 'path' => $path]);

        if ($path === false) {
            Log::error('File storage failed', ['file' => $file->getClientOriginalName()]);
            return response()->json(['error' => 'Failed to store the uploaded file.'], 500);
        }

        // Log and verify the stored file path using Storage
        $storagePath = Storage::disk('local')->path($path);
        Log::info('File stored via Storage', [
            'path' => $path,
            'storage_path' => $storagePath,
            'exists' => Storage::disk('local')->exists($path),
            'is_readable' => is_readable($storagePath),
            'is_writable' => is_writable($storagePath),
        ]);

        if (!Storage::disk('local')->exists($path)) {
            Log::error('File not found after storage', ['path' => $storagePath]);
            return response()->json(['error' => 'File storage failed. File not found.'], 500);
        }

        // OCR with Tesseract (works with JPG/PNG directly)
        try {
            $realPath = realpath($storagePath);
            Log::info('Real path for Tesseract', ['real_path' => $realPath]);
            $ocr = new TesseractOCR($realPath);
            $text = $ocr->run();
            Log::info('OCR completed', ['text' => $text]);
        } catch (\Exception $e) {
            Log::error('Tesseract OCR error: ' . $e->getMessage());
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path); // Cleanup temporary file if it exists
                Log::info('Cleaned up temporary file after OCR failure', ['path' => $path]);
            } else {
                Log::warning('File not found for cleanup after OCR failure', ['path' => $path]);
            }
            return response()->json(['error' => 'Failed to process the receipt with OCR: ' . $e->getMessage()], 500);
        }

        // Parse OCR text to extract data
        $text = strtolower(trim($text)); // Normalize text for easier parsing
        $date = $this->parseDate($text) ?: '2022-10-30'; // Default if not found
        $store = $this->parseStore($text) ?: 'Store';
        $paymentMethod = $this->parsePaymentMethod($text) ?: 'Card';
        $cost = $this->parseCost($text) ?: '69.69';

        Log::info('Parsed OCR data', [
            'date' => $date,
            'store' => $store,
            'payment_method' => $paymentMethod,
            'cost' => $cost,
        ]);

        // Use parsed data for file naming
        $newName = "{$date}_{$store}_{$paymentMethod}_\${$cost}.{$file->getClientOriginalExtension()}";
        $newPath = Storage::disk('local')->path('temp/' . $newName);

        try {
            if (Storage::disk('local')->move($path, 'temp/' . $newName)) {
                Log::info('File renamed/moved', ['new_path' => 'temp/' . $newName, 'exists' => Storage::disk('local')->exists('temp/' . $newName)]);
            } else {
                throw new \Exception('Failed to rename/move file');
            }
        } catch (\Exception $e) {
            Log::error('Failed to rename/move file: ' . $e->getMessage());
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path); // Cleanup if move fails
                Log::info('Cleaned up original file after rename/move failure', ['path' => $path]);
            }
            return response()->json(['error' => 'Failed to process the file: ' . $e->getMessage()], 500);
        }

        // Upload to Google Drive
        try {
            $client = new Client();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->refreshToken(config('services.google.refresh_token'));
            $client->setScopes([Drive::DRIVE]);
            $client->setAccessType('offline');

            $driveService = new Drive($client);

            $fileMetadata = new \Google\Service\Drive\DriveFile();
            $fileMetadata->setName($newName);
            $fileMetadata->setParents([config('services.google.folder_id')]); // Your Google Drive folder ID

            $content = Storage::disk('local')->get('temp/' . $newName);
            $mimeType = $file->getClientMimeType(); // e.g., 'image/jpeg' or 'image/png'
            $file = $driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,webViewLink',
            ]);

            $driveLink = $file->webViewLink; // Get the web viewable link
            Log::info('File uploaded to Google Drive', ['drive_link' => $driveLink]);
        } catch (\Exception $e) {
            Log::error('Failed to upload to Google Drive: ' . $e->getMessage());
            if (Storage::disk('local')->exists('temp/' . $newName)) {
                Storage::disk('local')->delete('temp/' . $newName); // Cleanup if upload fails
                Log::info('Cleaned up file after Google Drive upload failure', ['path' => 'temp/' . $newName]);
            }
            return response()->json(['error' => 'Failed to upload to Google Drive: ' . $e->getMessage()], 500);
        }

        // Save metadata with Google Drive link and OCR data
        try {
            $user = auth()->user();
            if ($user) {
                $user->receipts()->create([
                    'original_filename' => $originalFilename, // Use the original uploaded file, not the Google Drive file
                    'renamed_filename' => $newName,
                    'google_drive_link' => $driveLink,
                    'date' => $date, // Store parsed OCR data
                    'store' => $store,
                    'payment_method' => $paymentMethod,
                    'cost' => $cost,
                ]);
                Log::info('Receipt metadata saved', ['user_id' => $user->id]);
            } else {
                Log::warning('No authenticated user found for receipt upload');
                if (Storage::disk('local')->exists('temp/' . $newName)) {
                    Storage::disk('local')->delete('temp/' . $newName); // Cleanup if no user
                    Log::info('Cleaned up file after no user found', ['path' => 'temp/' . $newName]);
                }
                return response()->json(['error' => 'User not authenticated'], 401);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save receipt metadata: ' . $e->getMessage());
            if (Storage::disk('local')->exists('temp/' . $newName)) {
                Storage::disk('local')->delete('temp/' . $newName); // Cleanup on metadata save failure
                Log::info('Cleaned up file after metadata save failure', ['path' => 'temp/' . $newName]);
            }
            return response()->json(['error' => 'Failed to save receipt metadata: ' . $e->getMessage()], 500);
        }

        // Cleanup: Remove temporary file with existence check
        try {
            if (Storage::disk('local')->exists('temp/' . $newName)) {
                Storage::disk('local')->delete('temp/' . $newName);
                Log::info('Temporary file cleaned up', ['path' => 'temp/' . $newName]);
            } else {
                Log::warning('Temporary file not found for cleanup', ['path' => 'temp/' . $newName]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to clean up temporary file: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Receipt uploaded successfully', 'google_drive_link' => $driveLink]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $receipts = $user->receipts()->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $receipts]);
    }

    /**
     * Parse date from OCR text (example implementation, customize as needed).
     */
    private function parseDate($text)
    {
        // Look for common date formats (e.g., MM/DD/YYYY, YYYY-MM-DD, DD/MM/YYYY)
        if (preg_match('/\b(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}|\d{2}-\d{2}-\d{4})\b/', $text, $matches)) {
            $dateStr = $matches[1];
            try {
                return \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('Failed to parse date: ' . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    /**
     * Parse store name from OCR text (example implementation, customize as needed).
     */
    private function parseStore($text)
    {
        // Look for common store names or keywords (e.g., "store", "market", etc.)
        $storeKeywords = ['store', 'market', 'shop', 'supermarket', 'grocery'];
        foreach ($storeKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $parts = explode($keyword, $text, 2);
                if (isset($parts[1])) {
                    $storeName = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $parts[1]));
                    return ucfirst($storeName) ?: 'Store';
                }
            }
        }
        return 'Store';
    }

    /**
     * Parse payment method from OCR text (example implementation, customize as needed).
     */
    private function parsePaymentMethod($text)
    {
        // Look for common payment methods (e.g., "card", "cash", "credit", "debit")
        $paymentMethods = ['card', 'cash', 'credit', 'debit'];
        foreach ($paymentMethods as $method) {
            if (stripos($text, $method) !== false) {
                return ucfirst($method);
            }
        }
        return 'Card';
    }

    /**
     * Parse cost from OCR text (example implementation, customize as needed).
     */
    private function parseCost($text)
    {
        // Look for numbers with optional currency symbols (e.g., $123.45, 123.45)
        if (preg_match('/\b\$?(\d+\.\d{2})\b/', $text, $matches)) {
            return number_format(floatval($matches[1]), 2);
        }
        return '69.69';
    }
}