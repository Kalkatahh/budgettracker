<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Google\Client;

class ReceiptController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['receipt' => 'required|file|mimes:jpg,png,pdf']);
        $file = $request->file('receipt');
        $path = $file->store('temp');

        // OCR with Tesseract
        $ocr = new TesseractOCR(storage_path('app/' . $path));
        $text = $ocr->run();

        // Placeholder parsing (customize as needed)
        $date = '2024-01-01'; // Replace with real parsing
        $store = 'Store'; 
        $card = 'Card'; 
        $cost = '123.23'; 

        $newName = "{$date}_{$store}_{$card}_\${$cost}.{$file->extension()}";
        $newPath = storage_path('app/temp/' . $newName);
        rename(storage_path('app/' . $path), $newPath);

        // Upload to Google Drive (placeholder)
        $driveLink = 'https://drive.google.com/file/d/123'; // Implement later with Google API

        // Optional: Save metadata
        auth()->user()->receipts()->create([
            'original_filename' => $file->getClientOriginalName(),
            'renamed_filename' => $newName,
            'google_drive_link' => $driveLink,
        ]);

        unlink($newPath); // Cleanup
        return response()->json(['link' => $driveLink]);
    }
}