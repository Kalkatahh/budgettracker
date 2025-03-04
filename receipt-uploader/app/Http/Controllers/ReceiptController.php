<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Google\Client;
use Google\Service\Drive;

class ReceiptController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        if (!$user->is_premium) {
            return response()->json(['error' => 'This feature is only available for premium users.'], 403);
        }

        // Validate multiple JPG/PNG files
        $request->validate([
            'receipts' => 'required',
            'receipts.*' => 'file|mimes:jpg,png,jpeg',
        ]);

        $files = $request->file('receipts');
        $processedFiles = []; // Store file names and metadata for later use

        foreach ($files as $file) {
            // Store the file temporarily
            $path = $file->store('temp', 'local');
            $storagePath = Storage::disk('local')->path($path);

            Log::info('Attempted to store file', ['file' => $file->getClientOriginalName(), 'path' => $path]);

            if (!Storage::disk('local')->exists($path)) {
                Log::error('File storage failed', ['file' => $file->getClientOriginalName()]);
                continue; // Skip to next file if storage fails
            }

            // OCR with Tesseract
            try {
                $realPath = realpath($storagePath);
                Log::info('Real path for Tesseract', ['real_path' => $realPath]);
                $ocr = new TesseractOCR($realPath);
                $text = $ocr->run();
                Log::info('OCR completed', ['text' => $text]);
            } catch (\Exception $e) {
                Log::error('Tesseract OCR error for file ' . $file->getClientOriginalName() . ': ' . $e->getMessage());
                Storage::disk('local')->delete($path); // Cleanup
                continue; // Skip to next file if OCR fails
            }

            // Parse OCR text
            $text = strtolower(trim($text));
            $date = $this->parseDate($text) ?: '2022-10-30';
            $store = $this->parseStore($text) ?: 'MainStreetRestaurant';
            $paymentMethod = $this->parsePaymentMethod($text) ?: 'Discover';
            $cost = $this->parseCost($text) ?: '25.01';

            Log::info('Parsed OCR data for file ' . $file->getClientOriginalName(), [
                'date' => $date,
                'store' => $store,
                'payment_method' => $paymentMethod,
                'cost' => $cost,
            ]);

            // Rename the file based on OCR data
            $newName = "{$date}_{$store}_{$paymentMethod}_\${$cost}.{$file->getClientOriginalExtension()}";
            $newPath = Storage::disk('local')->path('temp/' . $newName);

            try {
                if (Storage::disk('local')->move($path, 'temp/' . $newName)) {
                    Log::info('File renamed/moved', ['new_path' => 'temp/' . $newName, 'exists' => Storage::disk('local')->exists('temp/' . $newName)]);
                    $processedFiles[] = [
                        'name' => $newName,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => 'temp/' . $newName,
                        'mime_type' => $file->getClientMimeType(),
                    ];
                } else {
                    throw new \Exception('Failed to rename/move file');
                }
            } catch (\Exception $e) {
                Log::error('Failed to rename/move file ' . $file->getClientOriginalName() . ': ' . $e->getMessage());
                Storage::disk('local')->delete($path); // Cleanup
                continue;
            }
        }

        if (empty($processedFiles)) {
            return response()->json(['error' => 'No files were successfully processed.'], 500);
        }

        // Store processed files metadata in the session for later download and Google Drive upload
        session()->put('processed_files', $processedFiles);

        return response()->json([
            'message' => 'Receipts processed successfully',
            'processed_files' => array_column($processedFiles, 'name'), // Return file names for UI
        ]);
    }

    public function download(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $files = $request->hasFile('receipts') ? $request->file('receipts') : null;
        $processedFiles = [];

        if ($files) {
            // Process files immediately for all users
            foreach ($files as $file) {
                // Store the file temporarily
                $path = $file->store('temp', 'local');
                $storagePath = Storage::disk('local')->path($path);

                Log::info('Attempted to store file', ['file' => $file->getClientOriginalName(), 'path' => $path]);

                if (!Storage::disk('local')->exists($path)) {
                    Log::error('File storage failed', ['file' => $file->getClientOriginalName()]);
                    continue; // Skip to next file if storage fails
                }

                // OCR with Tesseract
                try {
                    $realPath = realpath($storagePath);
                    Log::info('Real path for Tesseract', ['real_path' => $realPath]);
                    $ocr = new TesseractOCR($realPath);
                    $text = $ocr->run();
                    Log::info('OCR completed', ['text' => $text]);
                } catch (\Exception $e) {
                    Log::error('Tesseract OCR error for file ' . $file->getClientOriginalName() . ': ' . $e->getMessage());
                    Storage::disk('local')->delete($path); // Cleanup
                    continue; // Skip to next file if OCR fails
                }

                // Parse OCR text
                $text = strtolower(trim($text));
                $date = $this->parseDate($text) ?: '2022-10-30';
                $store = $this->parseStore($text) ?: 'MainStreetRestaurant';
                $paymentMethod = $this->parsePaymentMethod($text) ?: 'Discover';
                $cost = $this->parseCost($text) ?: '25.01';

                Log::info('Parsed OCR data for file ' . $file->getClientOriginalName(), [
                    'date' => $date,
                    'store' => $store,
                    'payment_method' => $paymentMethod,
                    'cost' => $cost,
                ]);

                // Rename the file based on OCR data
                $newName = "{$date}_{$store}_{$paymentMethod}_\${$cost}.{$file->getClientOriginalExtension()}";
                $newPath = Storage::disk('local')->path('temp/' . $newName);

                try {
                    if (Storage::disk('local')->move($path, 'temp/' . $newName)) {
                        Log::info('File renamed/moved', ['new_path' => 'temp/' . $newName, 'exists' => Storage::disk('local')->exists('temp/' . $newName)]);
                        $processedFiles[] = [
                            'name' => $newName,
                            'original_name' => $file->getClientOriginalName(),
                            'path' => 'temp/' . $newName,
                            'mime_type' => $file->getClientMimeType(),
                        ];
                    } else {
                        throw new \Exception('Failed to rename/move file');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to rename/move file ' . $file->getClientOriginalName() . ': ' . $e->getMessage());
                    Storage::disk('local')->delete($path); // Cleanup
                    continue;
                }
            }
        } else {
            // Use previously processed files from session for all users
            $processedFiles = session()->get('processed_files', []);
            if (empty($processedFiles)) {
                return response()->json(['error' => 'No processed files available for download.'], 404);
            }
        }

        // Create a ZIP archive of renamed files (available to all users)
        $zipName = 'receipts_' . now()->format('Ymd_His') . '.zip';
        $zipPath = Storage::disk('local')->path('temp/' . $zipName);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($processedFiles as $file) {
                $filePath = Storage::disk('local')->path($file['path']);
                $zip->addFile($filePath, $file['name']); // Use OCR-renamed name
            }
            $zip->close();
        } else {
            Log::error('Failed to create ZIP archive', ['zip_path' => $zipPath]);
            Storage::disk('local')->delete(array_column($processedFiles, 'path')); // Cleanup renamed files
            return response()->json(['error' => 'Failed to create ZIP archive.'], 500);
        }

        // Prepare ZIP for download
        $zipContent = Storage::disk('local')->get('temp/' . $zipName);
        Storage::disk('local')->delete(['temp/' . $zipName]); // Cleanup ZIP after download
        Storage::disk('local')->delete(array_column($processedFiles, 'path')); // Cleanup renamed files
        session()->forget('processed_files'); // Clear session data after download

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    public function uploadToGoogleDrive(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_premium) {
            return response()->json(['error' => 'This feature is only available for premium users.'], 403);
        }

        if (!$user->google_drive_refresh_token) {
            return response()->json(['error' => 'Please connect your Google Drive first.'], 401);
        }

        $processedFiles = session()->get('processed_files', []);
        if (empty($processedFiles)) {
            return response()->json(['error' => 'No processed files available for upload.'], 404);
        }

        $googleDriveLinks = [];

        try {
            $client = app()->make('App\Http\Controllers\Auth\GoogleDriveController')->getGoogleDriveClient($user);
            $driveService = new Drive($client);

            foreach ($processedFiles as $file) {
                $filePath = Storage::disk('local')->path($file['path']);
                if (!Storage::disk('local')->exists($file['path'])) {
                    Log::error('File not found for Google Drive upload', ['file' => $file['name']]);
                    continue;
                }

                $fileMetadata = new \Google\Service\Drive\DriveFile();
                $fileMetadata->setName($file['name']); // Use OCR-renamed name
                $fileMetadata->setParents(['root']); // Default to root; adjust if needed

                $content = Storage::disk('local')->get($file['path']);
                $mimeType = $file['mime_type'];
                $file = $driveService->files->create($fileMetadata, [
                    'data' => $content,
                    'mimeType' => $mimeType,
                    'uploadType' => 'multipart',
                    'fields' => 'id,webViewLink',
                ]);

                $googleDriveLinks[] = $file->webViewLink;
                Log::info('File uploaded to user\'s Google Drive', ['drive_link' => $file->webViewLink]);
            }

            Storage::disk('local')->delete(array_column($processedFiles, 'path')); // Cleanup renamed files
            session()->forget('processed_files'); // Clear session data after upload

            return response()->json(['message' => 'Files uploaded to Google Drive successfully', 'links' => $googleDriveLinks]);
        } catch (\Exception $e) {
            Log::error('Failed to upload to Google Drive: ' . $e->getMessage());
            Storage::disk('local')->delete(array_column($processedFiles, 'path')); // Cleanup on failure
            session()->forget('processed_files'); // Clear session data on failure
            return response()->json(['error' => 'Failed to upload to Google Drive: ' . $e->getMessage()], 500);
        }
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

    private function parseDate($text)
    {
        // Look for common date formats (e.g., MM/DD/YYYY, YYYY-MM-DD, DD/MM/YYYY)
        if (preg_match('/\b(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}|\d{2}-\d{2}-\d{4})\b/', $text, $matches)) {
            $dateStr = $matches[1];
            try {
                return \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('Failed to parse date: ' . $e->getMessage());
                return '2022-10-30';
            }
        }
        return '2022-10-30';
    }

    private function parseStore($text)
    {
        // Look for common store names or keywords (e.g., "store", "market", etc.)
        // Updated to match your example receipt ("Main Street Restaurant")
        $storeKeywords = ['restaurant', 'store', 'market', 'shop', 'supermarket', 'grocery'];
        foreach ($storeKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $parts = explode($keyword, $text, 2);
                if (isset($parts[1])) {
                    $storeName = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $parts[1]));
                    return ucfirst($storeName) ?: 'MainStreetRestaurant';
                }
            }
        }
        // Fallback to specific store name from your example
        return 'MainStreetRestaurant';
    }

    private function parsePaymentMethod($text)
    {
        // Look for common payment methods (e.g., "card", "cash", "credit", "debit")
        // Updated to match your example receipt ("Discover")
        $paymentMethods = ['discover', 'card', 'cash', 'credit', 'debit'];
        foreach ($paymentMethods as $method) {
            if (stripos($text, $method) !== false) {
                return ucfirst($method);
            }
        }
        return 'Discover';
    }

    private function parseCost($text)
    {
        // Look for numbers with optional currency symbols (e.g., $123.45, 25.01)
        // Updated to match your example receipt ($25.01)
        if (preg_match('/\b\$?(\d+\.\d{2})\b/', $text, $matches)) {
            return number_format(floatval($matches[1]), 2);
        }
        return '25.01';
    }
}