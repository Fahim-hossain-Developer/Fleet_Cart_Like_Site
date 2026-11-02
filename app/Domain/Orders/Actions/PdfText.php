<?php

namespace App\Domain\Orders\Actions;

use Illuminate\Support\HtmlString;
use Symfony\Component\Process\Process;

final class PdfText
{
    public function render(?string $text): HtmlString
    {
        $text = mb_substr($text ?? '', 0, 5000);
        if (! preg_match('/[\x{0980}-\x{09FF}]/u', $text)) {
            return new HtmlString(e($text));
        }
        $cache = storage_path('framework/cache/pdf-text');
        if (! is_dir($cache)) {
            mkdir($cache, 0700, true);
        }
        $path = $cache.'/'.hash('sha256', 'v2'.$text).'.png';
        if (! is_file($path)) {
            $tmp = $path.'.'.bin2hex(random_bytes(8)).'.png';
            $process = new Process(['pango-view', '--no-display', '--font=Noto Sans Bengali 13', '--dpi=144', '--width=360', '--wrap=word-char', '--margin=0', '--background=white', '--foreground=black', '--text='.$text, '--output='.$tmp], base_path(), ['FONTCONFIG_FILE' => config_path('pdf-fonts.conf'), 'LANG' => 'C.UTF-8', 'LC_ALL' => 'C.UTF-8']);
            $process->setTimeout(15);
            $process->mustRun();
            $image = imagecreatefrompng($tmp);
            $cropped = imagecropauto($image, IMG_CROP_WHITE);
            if ($cropped) {
                imagepng($cropped, $tmp);
                imagedestroy($cropped);
            }imagedestroy($image);
            rename($tmp, $path);
            chmod($path, 0600);
        }
        [$width,$height] = getimagesize($path);
        $data = base64_encode(file_get_contents($path));

        return new HtmlString('<img alt="'.e($text).'" style="vertical-align:middle;width:'.($width / 2).'px;height:auto;max-width:100%" src="data:image/png;base64,'.$data.'">');
    }
}
