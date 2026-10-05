<?php

namespace App\Services;

use App\Models\Product;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrService
{
    public function svg(Product $product): string
    {
        return (new QRCode(new QROptions(['outputInterface' => QRMarkupSVG::class, 'outputBase64' => false, 'addQuietzone' => true, 'quietzoneSize' => 4, 'drawCircularModules' => false])))->render($product->qr_token);
    }
}
