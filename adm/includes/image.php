<?php

class image
{

    // Variables
    private $img;
    private $img_src;
    private $format;
    private $quality = 75;
    private $x_input;
    private $y_input;
    private $x_output;
    private $y_output;
    private $resize;

	public function __construct ( $img ) {

        // Find format
        $ext = strtoupper(pathinfo($img, PATHINFO_EXTENSION));

        // JPEG image
        if(is_file($img) && ($ext == "JPG" OR $ext == "JPEG"))
        {

            $this->format = $ext;
            $this->img = ImageCreateFromJPEG($img);
            $this->img_src = $img;
            

        }

        // PNG image
        elseif(is_file($img) && $ext == "PNG")
        {

            $this->format = $ext;
            $this->img = ImageCreateFromPNG($img);
            $this->img_src = $img;

        }

        // GIF image
        elseif(is_file($img) && $ext == "GIF")
        {

            $this->format = $ext;
            $this->img = ImageCreateFromGIF($img);
            $this->img_src = $img;

        }

        // Get dimensions
        $this->x_input = imagesx($this->img);
        $this->y_input = imagesy($this->img);

    }

    // Set maximum image size (pixels)
    public function set_size($size = 100)
    {

        // Resize
        if($this->x_input > $size && $this->y_input > $size)
        {

            // Wide
            if($this->x_input >= $this->y_input)
            {

                $this->x_output = $size;
                $this->y_output = ($this->x_output / $this->x_input) * $this->y_input;

            }

            // Tall
            else
            {

                $this->y_output = $size;
                $this->x_output = ($this->y_output / $this->y_input) * $this->x_input;

            }

            // Ready
            $this->resize = TRUE;

        }

        // Don't resize
        else { 
			$this->resize = FALSE; 
		}

        if($this->resize)
        {

			$img_nuevo = ImageCreateTrueColor($this->x_output, $this->y_output);
            ImageCopyResampled($img_nuevo, $this->img, 0, 0, 0, 0, $this->x_output, $this->y_output, $this->x_input, $this->y_input);
			@ImageDestroy($this->img);
			$this->img = $img_nuevo;
			$this->x_input = imagesx($this->img);
			$this->y_input = imagesy($this->img);
			
        }

    }

    // Set maximum image size (pixels)
    public function set_size_wh($size_w = 100, $size_h = 100)
    {

        // Resize
        if($this->x_input > $size_w && $this->y_input > $size_h)
        {

            // Wide
            if($this->x_input >= $this->y_input)
            {

                $this->x_output = $size_w;
                $this->y_output = ($this->x_output / $this->x_input) * $this->y_input;

            }

            // Tall
            else
            {

                $this->y_output = $size_h;
                $this->x_output = ($this->y_output / $this->y_input) * $this->x_input;

            }

            // Ready
            $this->resize = TRUE;

        }

        // Don't resize
        else { 
            $this->resize = FALSE; 
        }

        if($this->resize)
        {

            $img_nuevo = ImageCreateTrueColor($this->x_output, $this->y_output);
            ImageCopyResampled($img_nuevo, $this->img, 0, 0, 0, 0, $this->x_output, $this->y_output, $this->x_input, $this->y_input);
            @ImageDestroy($this->img);
            $this->img = $img_nuevo;
            $this->x_input = imagesx($this->img);
            $this->y_input = imagesy($this->img);
            
        }

    }

    // Set image quality (JPEG only)
    public function set_quality($quality)
    {

        if(is_int($quality))
        {

            $this->quality = $quality;

        }

    }

    // Save image
    public function save_img($path)
    {

		imageJPEG($this->img, $path, $this->quality);

    }

    // Clear image cache
    public function clear()
    {

        @ImageDestroy($this->img);

    }

    //scalar imagen
    public function scale_image($src_image, $dst_image, $op = 'fit') {
        $src_width = imagesx($src_image);
        $src_height = imagesy($src_image);
    
        $dst_width = imagesx($dst_image);
        $dst_height = imagesy($dst_image);
    
        // Try to match destination image by width
        $new_width = $dst_width;
        $new_height = round($new_width*($src_height/$src_width));
        $new_x = 0;
        $new_y = round(($dst_height-$new_height)/2);
    
        // FILL and FIT mode are mutually exclusive
        if ($op =='fill')
            $next = $new_height < $dst_height; else $next = $new_height > $dst_height;
    
        // If match by width failed and destination image does not fit, try by height 
        if ($next) {
            $new_height = $dst_height;
            $new_width = round($new_height*($src_width/$src_height));
            $new_x = round(($dst_width - $new_width)/2);
            $new_y = 0;
        }
    
        // Copy image on right place
        ImageCopyResampled($dst_image, $src_image , $new_x, $new_y, 0, 0, $new_width, $new_height, $src_width, $src_height);
    }

}