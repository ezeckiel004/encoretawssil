<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

class UtilsController extends Controller
{
    /**
     * Upload une photo et retourne son chemin.
     */
    public function uploadPhoto(Request $request, $fieldName = 'photo'): ?string
    {
        if ($request->hasFile($fieldName)) {
            $file = $request->file($fieldName);
            
            // Log information about the upload attempt
            \Log::info('Photo upload attempt', [
                'field_name' => $fieldName,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError()
            ]);
            
            $path = $file->store('photos', 'public'); // Stocke dans storage/app/public/photos
            
            \Log::info('Photo uploaded successfully', ['path' => $path]);
            
            return $path;
        }
        
        \Log::warning('No file found for field', ['field_name' => $fieldName]);
        return null;
    }

    
    /**
     * Upload un fichier et retourne son chemin.
     */
    public function uploadFile(Request $request, $fieldName): ?string
    {
        if ($request->hasFile($fieldName)) {
            $file = $request->file($fieldName);
            $path = $file->store('documents', 'public'); // Stocke dans storage/app/public/documents
            return $path;
        }
        return null;
    }
    }