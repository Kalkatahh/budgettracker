<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        // Validate request
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        // Store uploaded file temporarily
        $file = $request->file('file');
        $tempPath = $file->store('temp');

        // Perform OCR on the image using Tesseract
        $ocrResult = (new TesseractOCR(storage_path('app/' . $tempPath)))->run();

        // Extract details from OCR result
        $date = $this->extractDate($ocrResult);
        $store = $this->extractStore($ocrResult);
        $card = $this->extractCard($ocrResult);
        $cost = $this->extractCost($ocrResult);

        if (!$date || !$store || !$card || !$cost) {
            return response()->json(['message' => 'Failed to extract receipt details'], 422);
        }

        // Rename file based on extracted details
        $newFileName = "{$date}_{$store}_{$card}_\${$cost}.{$file->getClientOriginalExtension()}";

        // Move the renamed file to a permanent location (e.g., 'receipts' folder)
        Storage::move($tempPath, "receipts/{$newFileName}");

        return response()->json([
            'message' => 'File processed successfully',
            'filename' => $newFileName,
        ]);
    }

    private function extractDate($text)
    {
        preg_match('/\d{4}-\d{2}-\d{2}/', $text, $matches);
        return $matches[0] ?? null;
    }

    private function extractStore($text)
    {
        // Add logic to identify store name (e.g., match known store names)
        // Example: Match "Costco" or other stores in the text
        preg_match('/(Costco|Walmart|Target|Amazon)/i', $text, $matches);
        return $matches[0] ?? 'UnknownStore';
    }

    private function extractCard($text)
    {
        // Add logic to identify credit card used (e.g., match known card names)
        preg_match('/(CIBC|Visa|Mastercard|Amex)/i', $text, $matches);
        return $matches[0] ?? 'UnknownCard';
    }

    private function extractCost($text)
    {
        preg_match('/\$\d+(\.\d{2})?/', $text, $matches);
        return str_replace('$', '', $matches[0] ?? null);
    }
}
