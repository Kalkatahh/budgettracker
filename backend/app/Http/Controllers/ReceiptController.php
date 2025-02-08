<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:pdf,jpeg,png|max:10240' // 10MB
    ]);

    $file = $request->file('file');
    $path = $file->store('receipts', 'public');

    $receipt = Receipt::create([
        'original_name' => $file->getClientOriginalName(),
        'path' => $path,
        'mime_type' => $file->getMimeType(),
        'size' => $file->getSize()
    ]);

    return response()->json([
        'message' => 'File uploaded successfully',
        'receipt' => $receipt
    ]);
}

}
