<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Illuminate\Support\Facades\Log;

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
        $path = $file->store('temp');

        // Log and verify the stored file path
        $fullPath = storage_path('app/' . $path);
        Log::info('File stored', [
            'path' => $path,
            'full_path' => $fullPath,
            'exists' => file_exists($fullPath),
            'is_readable' => is_readable($fullPath),
            'is_writable' => is_writable($fullPath),
        ]);

        if (!file_exists($fullPath)) {
            Log::error('File not found after storage', ['path' => $fullPath]);
            return response()->json(['error' => 'File storage failed. File not found.'], 500);
        }

        // OCR with Tesseract (works with JPG/PNG directly)
        try {
            //$ocr = new TesseractOCR($fullPath);
            $realPath = realpath($fullPath);
            Log::info('Real path for Tesseract', ['real_path' => $realPath]);
            $ocr = new TesseractOCR($realPath);
            $text = $ocr->run();
            Log::info('OCR completed', ['text' => $text]);
        } catch (\Exception $e) {
            Log::error('Tesseract OCR error: ' . $e->getMessage());
            // Check if file exists before unlinking
            if (file_exists($fullPath)) {
                unlink($fullPath); // Cleanup temporary file if it exists
                Log::info('Cleaned up temporary file after OCR failure', ['path' => $fullPath]);
            } else {
                Log::warning('File not found for cleanup after OCR failure', ['path' => $fullPath]);
            }
            return response()->json(['error' => 'Failed to process the receipt with OCR: ' . $e->getMessage()], 500);
        }

        // Placeholder parsing (customize as needed)
        $date = '2024-01-01'; // Replace with real parsing logic
        $store = 'Store';
        $card = 'Card';
        $cost = '123.23';

        $newName = "{$date}_{$store}_{$card}_\${$cost}.{$file->getClientOriginalExtension()}";
        $newPath = storage_path('app/temp/' . $newName);

        try {
            if (rename($fullPath, $newPath)) {
                Log::info('File renamed', ['new_path' => $newPath, 'exists' => file_exists($newPath)]);
            } else {
                throw new \Exception('Failed to rename file');
            }
        } catch (\Exception $e) {
            Log::error('Failed to rename file: ' . $e->getMessage());
            if (file_exists($fullPath)) {
                unlink($fullPath); // Cleanup if rename fails
                Log::info('Cleaned up original file after rename failure', ['path' => $fullPath]);
            }
            return response()->json(['error' => 'Failed to process the file: ' . $e->getMessage()], 500);
        }

        // Upload to Google Drive (placeholder)
        $driveLink = 'https://drive.google.com/file/d/1fzYwVfOsLuhrnkxLpfmkl_WUOYBT-7Aq'; // Implement later with Google API

        // Save metadata (ensure user is authenticated and receipts relationship exists)
        try {
            $user = auth()->user();
            if ($user) {
                $user->receipts()->create([
                    'original_filename' => $file->getClientOriginalName(),
                    'renamed_filename' => $newName,
                    'google_drive_link' => $driveLink,
                ]);
                Log::info('Receipt metadata saved', ['user_id' => $user->id]);
            } else {
                Log::warning('No authenticated user found for receipt upload');
                if (file_exists($newPath)) {
                    unlink($newPath); // Cleanup if no user
                    Log::info('Cleaned up file after no user found', ['path' => $newPath]);
                }
                return response()->json(['error' => 'User not authenticated'], 401);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save receipt metadata: ' . $e->getMessage());
            if (file_exists($newPath)) {
                unlink($newPath); // Cleanup on metadata save failure
                Log::info('Cleaned up file after metadata save failure', ['path' => $newPath]);
            }
            return response()->json(['error' => 'Failed to save receipt metadata: ' . $e->getMessage()], 500);
        }

        // Cleanup: Remove temporary file with existence check
        try {
            if (file_exists($newPath)) {
                unlink($newPath);
                Log::info('Temporary file cleaned up', ['path' => $newPath]);
            } else {
                Log::warning('Temporary file not found for cleanup', ['path' => $newPath]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to clean up temporary file: ' . $e->getMessage());
        }

        return response()->json(['link' => $driveLink]);
    }
}