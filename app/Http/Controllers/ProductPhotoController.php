<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves uploaded product photos through PHP instead of the public/storage symlink,
 * because on the live cPanel host the web server can't follow that symlink into the app folder.
 */
class ProductPhotoController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        // Uploaded photos get a unique random filename, so a stored path never changes content.
        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
