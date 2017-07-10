<?php

namespace Netrunners\Model;

class TextToImage {

    private $img;

    /**
     * @param $text
     * @param int $fontSize
     * @param int $imgWidth
     * @param int $imgHeight
     * @param int $angle
     * @return bool
     */
    function createImage($text, $fontSize = 10, $imgWidth = 150, $imgHeight = 14, $angle = 0){

        $font = getcwd() . '/public/fonts/monospace.ttf';
        $this->img = imagecreatetruecolor($imgWidth, $imgHeight);
        $white = imagecolorallocate($this->img, 255, 255, 255);
        $grey = imagecolorallocate($this->img, 128, 128, 128);
        $black = imagecolorallocate($this->img, 0, 0, 0);
        imagefilledrectangle($this->img, 0, 0, $imgWidth - 1, $imgHeight - 1, $black);

        //break lines
        $splitText = explode ( "\\n" , $text );
        $lines = count($splitText);

        foreach($splitText as $txt){
            $textBox = imagettfbbox($fontSize,$angle,$font,$txt);
            $textWidth = abs(max($textBox[2], $textBox[4]));
            $textHeight = abs(max($textBox[5], $textBox[7]));
            $x = (imagesx($this->img) - $textWidth)/2;
            $y = ((imagesy($this->img) + $textHeight)/2)-($lines-2)*$textHeight;
            $lines = $lines-1;

            //add some shadow to the text
            imagettftext($this->img, $fontSize, $angle, $x, 12, $grey, $font, $txt);

            //add the text
            imagettftext($this->img, $fontSize, $angle, $x, 12, $white, $font, $txt);
        }
        return true;
    }

    /**
     * Display image
     */
    function showImage(){
        header('Content-Type: image/png');
        return imagepng($this->img);
    }

    /**
     * @param string $fileName
     * @param string $location
     * @return bool
     */
    function saveAsPng($fileName = 'text-image', $location = ''){
        $fileName = $fileName.".png";
        $fileName = !empty($location)?$location.$fileName:$fileName;
        return imagepng($this->img, $fileName);
    }

    /**
     * @param string $fileName
     * @param string $location
     * @return bool
     */
    function saveAsJpg($fileName = 'text-image', $location = ''){
        $fileName = $fileName.".jpg";
        $fileName = !empty($location)?$location.$fileName:$fileName;
        return imagejpeg($this->img, $fileName);
    }

}
