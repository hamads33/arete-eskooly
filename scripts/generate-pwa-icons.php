<?php
declare(strict_types=1);

$sourcePath = dirname(__DIR__) . '/public/assets/images/arete-logo.png';
$targetDir = dirname(__DIR__) . '/public/assets/pwa';
if (!extension_loaded('gd') || !is_file($sourcePath)) {
    fwrite(STDERR, "GD and the Arete source logo are required.\n");
    exit(1);
}
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    throw new RuntimeException('Could not create the PWA icon directory.');
}

$source = imagecreatefrompng($sourcePath);
if (!$source) throw new RuntimeException('Could not read the Arete logo.');
$sourceWidth = imagesx($source);
$sourceHeight = imagesy($source);

$render = static function (int $size, string $filename, float $logoRatio) use ($source, $sourceWidth, $sourceHeight, $targetDir): void {
    $canvas = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    $max = (int) round($size * $logoRatio);
    $scale = min($max / $sourceWidth, $max / $sourceHeight);
    $width = max(1, (int) round($sourceWidth * $scale));
    $height = max(1, (int) round($sourceHeight * $scale));
    $x = (int) (($size - $width) / 2);
    $y = (int) (($size - $height) / 2);
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
    imagepng($canvas, $targetDir . '/' . $filename, 9);
    imagedestroy($canvas);
};

$render(192, 'icon-192.png', 0.72);
$render(512, 'icon-512.png', 0.72);
$render(512, 'icon-maskable-512.png', 0.58);
imagedestroy($source);
