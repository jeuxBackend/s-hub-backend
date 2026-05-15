<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Traits\ResponsesTrait;
use Illuminate\Http\Request;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, ResponsesTrait;

    protected function handleUserFileUpload(Request $request, $fieldName, $directory)
    {
        if ($request->hasFile($fieldName)) {
            $fileName = rand() . time() . '.' . $request->file($fieldName)->extension();

            // Store file in public/candidatefiles
            $request->file($fieldName)->move(public_path('candidatefiles'), $fileName);

            // Return only file name (to save in DB)
            return $fileName;
        }

        return null;
    }

    protected function handleFileUpload(Request $request, $fieldName, $directory)
    {
        if ($request->hasFile($fieldName)) {
            $fileName = rand() . time() . '.' . $request->file($fieldName)->extension();
            $request->file($fieldName)->move(public_path($directory), $fileName);
            $fileName = $directory . '/' . $fileName;
            return "{$fileName}";
        }
        return null;
    }
}
