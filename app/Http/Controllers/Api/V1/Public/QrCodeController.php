<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = (string) $request->query('data', '');

        if ($data === '' || strlen($data) > 500) {
            abort(422, 'Invalid QR data.');
        }

        // Only allow absolute http(s) URLs or relative site paths.
        if (! preg_match('#^(https?://|/verify|/members/)#i', $data)) {
            abort(422, 'Unsupported QR payload.');
        }

        $svg = QrCode::format('svg')->size(160)->margin(1)->generate($data);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
