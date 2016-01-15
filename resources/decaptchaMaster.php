<?php
  abstract class decaptchaMaster {
    /**
     * The captcha URL
     * @var string
     */
    protected $urlCaptcha;

    /**
     * The local path where captcha images will be saved & image editing will be done
     * @var string
     */
    protected $workingDirectory;

    /**
     * The filename of the captcha image saved on the local server, in the $workingDirectory location
     * This variable is assigned during imageDownload() execution. It should be equal to $CONST_filenameCaptcha . ".ext"
     * @var string
     */
    protected $filenameCaptcha;

    /**
     * The filename used to write the original captcha file on the disk
     * @var string
     */
    protected $CONST_filenameCaptcha = "original_captcha";

    /**
     * Filenames for unknown characters images will start with this string
     * @var string
     */
    protected $CONST_unknownChar = "-";

    /**
     * Boolean used to display (if TRUE) or hide (if FALSE) verbose information
     * @var boolean
     */
    protected $verbose = FALSE;

    /**
     * decaptchaMaster constructor
     * @param string $urlCaptcha       The captcha URL. The image type must be a png, jpg, jpeg or gif file. It's fine if the url extension is different from the image type (ex: http://url/file.php for a generated png image captcha)
     * @param string $workingDirectory The local fullpath where captcha images will be saved & image editing will be done. Default value: 'temp/'
     * @param array  $curlOptions      This array contains few options used by curl to download the captcha: 'userAgent', 'referer' & 'cookie' (CURLOPT_COOKIEJAR). Those are optional.
     * @param bool    $verbose          Boolean used to display (if TRUE) or hide (if FALSE) verbose information. Default value: FALSE
     */
    public function __construct($urlCaptcha, $workingDirectory = 'temp/', $curlOptions = array(), $verbose = FALSE) {
      $this->verbose = $verbose;

      if(filter_var($urlCaptcha, FILTER_VALIDATE_URL) === FALSE)
        throw new Exception("decaptchaMaster __construct: $urlCaptcha is not a valid URL");

      if(is_dir($workingDirectory) === FALSE)
        throw new Exception("decaptchaMaster __construct: $workingDirectory is not a valid directory");

      $this->urlCaptcha = $urlCaptcha;
      if($this->verbose) { echo "V: decaptchaMaster __construct: captcha URL=".$this->urlCaptcha.'<br />'."\r\n"; }

      $this->workingDirectory = $workingDirectory;
      if(chdir($this->workingDirectory) === FALSE)
        throw new Exception("decaptchaMaster __construct: change current directory failed");
      if($this->verbose) { echo "V: decaptchaMaster __construct: Working directory=".getcwd().'<br />'."\r\n"; }

      $this->imageDownload($curlOptions);

      if($this->verbose) { echo "V: decaptchaMaster __construct: Done <br /><br />"."\r\n"; }
    }

    /**
     * Use curl to download the image (or captcha) locally on the server. It also downloads the session cookie if a file path is specified in the parameter $curlOptions['cookie'].
     * The image type must be a png, jpg, jpeg or gif
     * @param array $curlOptions contains few options used by curl to download the captcha: 'url', userAgent', 'referer' & 'cookie' (CURLOPT_COOKIEJAR). Those are optional. If no 'url' is specified, it will use $this->urlCaptcha
     */
    protected function imageDownload($curlOptions = array()) {

      if(!isset($curlOptions['url']) || empty($curlOptions['url']))
        $url = $this->urlCaptcha;
      else
        $url = $curlOptions['url'];

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_HEADER, 0);

      if(isset($curlOptions['userAgent']) && !empty($curlOptions['userAgent'])) 
        curl_setopt($ch, CURLOPT_USERAGENT, $curlOptions['userAgent']);

      if(isset($curlOptions['referer']) && !empty($curlOptions['referer']))
        curl_setopt($ch, CURLOPT_REFERER, $curlOptions['referer']);
            
      if(isset($curlOptions['cookie']) && !empty($curlOptions['cookie'])) {
        if(!file_exists($curlOptions['cookie'])) {                              //if the file doesn't exist, curl will fail to write the cookie content in the file, without displaying any error
            $handle = fopen($curlOptions['cookie'], 'w') or die('Cannot create the file: '.$curlOptions['cookie']);
            fclose($handle);
        }

        curl_setopt($ch, CURLOPT_COOKIEJAR, realpath($curlOptions['cookie']));  //write the connexion cookie to the file
        curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
        if($this->verbose) { echo "V: decaptchaMaster imageDownload: cookie=".realpath($curlOptions['cookie']).'<br />'."\r\n"; }
      }

      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);                            //follow HTTP 3xx redirects
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                              //return the result in a variable
      $captcha = curl_exec($ch);

      switch (curl_getinfo($ch, CURLINFO_CONTENT_TYPE)) {                       //the file type must be an image
        case 'image/png' :
        case 'image/jpg' :
        case 'image/jpeg':
        case 'image/gif' :
          $this->filenameCaptcha = $this->CONST_filenameCaptcha . '.' . substr(curl_getinfo($ch, CURLINFO_CONTENT_TYPE), strripos(curl_getinfo($ch, CURLINFO_CONTENT_TYPE),'/')+1); //filename.ext
          if($this->verbose) { echo "V: decaptchaMaster imageDownload: captcha=".realpath($this->filenameCaptcha).'<br />'."\r\n"; }
          break;

        default:
          if($this->verbose) { echo "V: decaptchaMaster imageDownload: content type=".curl_getinfo($ch, CURLINFO_CONTENT_TYPE).'<br />'."\r\n"; }
          throw new Exception("decaptchaMaster imageDownload: The content type doesn't match a known image file type");
          break;
      }
      curl_close($ch);                                                          //close the curl session
      
      if(isset($this->filenameCaptcha) && !empty($this->filenameCaptcha)) {
        $handle = fopen($this->filenameCaptcha, 'w') or die('Cannot create nor open the file: '.$this->filenameCaptcha);
        fwrite($handle, $captcha);                                              //write the image content inside
        fclose($handle);                                                        //close the file
        if($this->verbose) { echo "V: decaptchaMaster imageDownload: <img src='".$this->workingDirectory.$this->filenameCaptcha."?r=".mt_rand(0,999999)."' />".'<br />'."\r\n"; } //mt_rand is used to reset any server side cache system
      }
      if($this->verbose) { echo "V: decaptchaMaster imageDownload: Done <br /><br />"."\r\n"; }
    }

    /**
     * Returns an image resource of the imageSource in black and white strict (aka no greyish pixels, it's only black or white), based on a minimum amount of RGB amount
     * For example, if the pixel red amount is strictly below $color['r'] value (aka <$color['r']), it will be converted in black. If not, the pixel will be white (aka >=$color['r'])
     * Optionally, this function can also write the black and white image result on the disk
     * @param  integer $color          Default value is array('r'=>100,'g'=>100,'b'=>100). Contains for each r, g, b, the minimum amount of color needed to convert into a white pixel. For example, the pixel red color is below the amount of $color['r'], it will be converted in black.
     * @param  string  $filenameResult the path or filename on the local server to write the generated black and white picture. Optional, if not set the image won't be create on the local server
     * @param  string  $imageSource    the path or filename to the picture we want to get in black and white. Optional, if not set the default value is $this->filenameCaptcha
     * @return resource                 return a gd image resource similare to the image source, but in black and white strict
     */
    protected function imageBlackAndWhite($color = array('r'=>100,'g'=>100,'b'=>100), $filenameResult = '', $imageSource = '')
    {
      //$imageSource can be empty or a string
      if(!isset($imageSource) || empty($imageSource))  //default value for $imageSource is the local copy of the captcha
        $imageSource =  $this->filenameCaptcha;

      if(!is_string($imageSource)  || empty($imageSource)) //empty() because i don't trust the value of $this->filenameCaptcha
        throw new Exception("decaptchaMaster imageBlackAndWhite: The imageSource is not a valid string");

      $img = $this->imageCreateFrom($imageSource);

      if(get_resource_type($img) != 'gd')
        throw new Exception("decaptchaMaster imageBlackAndWhite: The image source can not be found or loaded");

      $white = imagecolorallocate($img, 255, 255, 255);
      $black = imagecolorallocate($img, 0, 0, 0);

      for ($y=0; $y<imagesy($img); $y++) {    //we browse the picture per lines
        for ($x=0; $x<imagesx($img); $x++) {
          $rgb = imagecolorat($img, $x, $y);  //get the pixel color
          $r = ($rgb >> 16) & 0xFF;
          $g = ($rgb >> 8)   & 0xFF;
          $b = $rgb         & 0xFF;
          
          if($r < $color['r'] || $g < $color['g'] || $b < $color['b'])
            imagesetpixel($img,$x,$y,$black);
          else
            imagesetpixel($img,$x,$y,$white);
        }
      }
      if(isset($filenameResult) && !empty($filenameResult))
        $this->imageWriteOnDisk($img, $filenameResult);
      
      if($this->verbose) { echo "V: decaptchaMaster imageBlackAndWhite: Done <br /><br />"."\r\n"; }
      return $img;
    }

    /**
     * Return an image resource based on the optional path or filename in parameter
     * @param  string $imageSource   the path or filename to the picture we want to get the imageResource. Optional, if not set the default value is $this->filenameCaptcha
     * @return resource             return a gd image resource
     */
    protected function imageCreateFrom($imageSource = '') {
      if(!isset($imageSource) || empty($imageSource))  //default value for $imageSource is the local copy of the captcha
        $imageSource =  $this->filenameCaptcha;

      $img = FALSE;
      switch (exif_imagetype($imageSource)) {
        case 1 :
          $img = imageCreateFromGif($imageSource);
          break;
        case 2 : //jpg & jpeg
           $img = imageCreateFromJpeg($imageSource);
          break;
        case 3 :
            $img = imageCreateFromPng($imageSource);
        break;

        default:
          throw new Exception("decaptchaMaster imageCreateFrom: The content type doesn't match a known image file type");
          break;
      }
      if($this->verbose) { echo "V: decaptchaMaster imageCreateFrom: Done <br /><br />"."\r\n"; }
      return $img;
    }

    /**
     * Write a gd image resource $imageResource on the local server at the location $this->workingDirectory/$filename
     * Since we can't guess the file type from a gd image resource, the function guess it from the $filename extension
     * @param  resource $imageResource gd image resource
     * @param  string   $filename      the image filename.ext. Allowed extensions are ".png", ".jpg", ".jpeg" and ".gif"
     */
    protected function imageWriteOnDisk($imageResource, $filename) {
      if(!isset($imageResource) || empty($imageResource) || get_resource_type($imageResource) != 'gd')
        throw new Exception("decaptchaMaster imageWriteOnDisk: The image resource is incorrect");

      if(!isset($filename) || empty($filename) || !is_string($filename) || strripos($filename,'.') === FALSE)
        throw new Exception("decaptchaMaster imageWriteOnDisk: The filename is incorrect. It should look like 'name.ext'");

      switch (substr(strtolower($filename), strripos($filename,'.')+1)) {
        case 'png' :
          imagepng($imageResource, $filename);
          break;
        case 'jpg' :
        case 'jpeg':
          imagejpeg($imageResource, $filename);
          break;
        case 'gif' :
          imagegif($imageResource, $filename);
        break;

        default:
          throw new Exception("decaptchaMaster imageWriteOnDisk: The filename extension doesn't match a known image file type");
          break;
      }
      if($this->verbose) { echo "V: decaptchaMaster imageWriteOnDisk: <img src='".$this->workingDirectory.$filename."?r=".mt_rand(0,999999)."' />".'<br />'."\r\n"; } //mt_rand is used to reset any server side cache system
      if($this->verbose) { echo "V: decaptchaMaster imageWriteOnDisk: Done <br /><br />"."\r\n"; }
    }

    /**
     * An image is just a 2 dimensions array of pixels. Each pixels can be defined by 2 coordinates (x,y) and 1 color. Each x represent a column index and each y represent a line index
     * The huge assumption here is that each part of the image (aka each letters) are separated by, at least, 1 column fully colored in $backgroundColor color.
     * It means that if 2 parts (or letters) share 1 (or ore) identical column index, the result of imageSlice() won't be correct.
     * Also, each columns for a single part of the image (or letter) must have, at least, 1 pixel different from $backgroundColor. If not, this particular part (or letter) will be slice in 2
     * @param  resource $imageResource   gd image resource
     * @param  array    $backgroundColor array of RGB value. Optional, default is white color: array('r'=>255,'g'=>255,'b'=>255)
     * @param  string   $filenameResult  the path or filename on the local server to write each sliced pictures. Optional, if not set slices won't be created on the local server
     * @return array                     return an array of gd image resources
     */
    protected function imageSlice($imageResource, $backgroundColor = array('r'=>255,'g'=>255,'b'=>255), $filenameResult = '') {
      if(!isset($imageResource) || empty($imageResource) || get_resource_type($imageResource) != 'gd')
        throw new Exception("decaptchaMaster imageSlice: The image resource is incorrect");

      $return = array();
      $backgroundIndex = imagecolorresolve($imageResource, $backgroundColor['r'], $backgroundColor['g'], $backgroundColor['b']);

      //A slice is 2 coordinates within the picture: "top left" pixel and "bottom right" pixel of the slice we want to cut
      $sliceRaw = array();    //sliceRaw contains a "non trimmed" slice. y will basically be 0 or imagesy($imageResource)
      $lastSliceColumn;       //the last slice column contains the last x coordinate containing a pixel different from the background (aka, belonging to a letter)
      $nbSlice = 0;            //count the number of slices/letter we found

      for($x=0; $x<imagesx($imageResource); $x++)
      {
        for($y=0; $y<imagesy($imageResource); $y++)
        {
          if(imagecolorat($imageResource,$x,$y) !== $backgroundIndex) { //if the current pixel is different from the background color
            if(empty($sliceRaw))                                        //if we reach the pixel the most on the left of the slice/letter
              $sliceRaw['topLeft'] = ['x'=>$x,'y'=>0];                  //we save it. Why y=0 and not $y? because of letters like J
            $lastSliceColumn = $x;                                      //we save here the last known column number (yet) which belong to a slice/letter
          }
        }

        //here, we reached the last pixel of the current column
        if(isset($lastSliceColumn) && $x != $lastSliceColumn) {             //if i'm already looking for the end of a slice/letter AND the current column (that we just finished parsing) is not part of the current slice/letter
          //it means that i reached the end of the slice/letter
          $nbSlice++;
          $sliceWidth = $lastSliceColumn - $sliceRaw['topLeft']['x'] + 1;
          $slice = imagecreatetruecolor ($sliceWidth, imagesy($imageResource));        //we create the slice image (empty)
          imagecopy($slice,$imageResource,0,0,$sliceRaw['topLeft']['x'],$sliceRaw['topLeft']['y'],$sliceWidth,imagesy($imageResource));  //we copy part of $imageResource within $slice

          if(isset($filenameResult) && !empty($filenameResult)) {
            $pos = strrpos($filenameResult, '.');  //we search for the last occurence of '.' in the filename
            if($pos !== false){                   //if we find it
              $filenameSlice = substr_replace($filenameResult, $nbSlice.'.', $pos, 1);
              $this->imageWriteOnDisk($slice, $filenameSlice);
            }
          }
          $return[$nbSlice] = $slice;  //the slice will be returned

          //we re-initialize variables for the next slice/letter
          unset($slice);
          $sliceRaw = array();
          unset($lastSliceColumn);
        }
      }
      if($this->verbose) { echo "V: decaptchaMaster imageSlice: Done <br /><br />"."\r\n"; }
      return $return;
    }

    /**
     * Used to trim an image. If the edges of the $imageResource are the same color as $color, this function will remove those part. Optionally, the trimmed result can be written on the local disk
     * @param  resource $imageResource  gd image resource
     * @param  array    $color          array of RGB value. Optional, default is white color: array('r'=>255,'g'=>255,'b'=>255)
     * @param  string   $filenameResult the path or filename on the local server to write the trimmed image. Optional, if not set the trimmed image won't be created on the local server
     * @return resource                 the trimmed gd image resource
     */
    protected function imageTrim($imageResource, $color = array('r'=>255,'g'=>255,'b'=>255), $filenameResult = '') {
      if(!isset($imageResource) || empty($imageResource) || get_resource_type($imageResource) != 'gd')
        throw new Exception("decaptchaMaster imageTrim: The image resource is incorrect");

      //find the borders positions
      $b_top = 0;
      $b_btm = 0;
      $b_lft = 0;
      $b_rt = 0;
      $colorIndex = imagecolorresolve($imageResource, $color['r'], $color['g'], $color['b']);

      //top
      for(; $b_top < imagesy($imageResource); $b_top++) {
        for($x = 0; $x < imagesx($imageResource); $x++) {
          if(imagecolorat($imageResource, $x, $b_top) != $colorIndex)
             break 2; //out of the 'top' loop
        }
      }
      //bottom
      for(; $b_btm < imagesy($imageResource); $b_btm++) {
        for($x = 0; $x < imagesx($imageResource); $x++) {
          if(imagecolorat($imageResource, $x, imagesy($imageResource) - $b_btm-1) != $colorIndex)
             break 2; //out of the 'bottom' loop
        }
      }
      //left
      for(; $b_lft < imagesx($imageResource); $b_lft++) {
        for($y = 0; $y < imagesy($imageResource); $y++) {
          if(imagecolorat($imageResource, $b_lft, $y) != $colorIndex)
             break 2; //out of the 'left' loop
        }
      }
      //right
      for(; $b_rt < imagesx($imageResource); ++$b_rt) {
        for($y = 0; $y < imagesy($imageResource); ++$y) {
          if(imagecolorat($imageResource, imagesx($imageResource) - $b_rt-1, $y) != $colorIndex)
             break 2; //out of the 'right' loop
        }
      }
      //copy the contents, excluding the border
      $return = imagecreatetruecolor(imagesx($imageResource)-($b_lft+$b_rt), imagesy($imageResource)-($b_top+$b_btm));
      imagecopy($return, $imageResource, 0, 0, $b_lft, $b_top, imagesx($return), imagesy($return));

      if(isset($filenameResult) && !empty($filenameResult))
        $this->imageWriteOnDisk($return, $filenameResult);
      
      if($this->verbose) { echo "V: decaptchaMaster imageTrim: Done <br /><br />"."\r\n"; }
      return $return;
    }

    /**
     * Returns an array(array(chars)) matching the $imageResource gd resource. $match contains for each char, what is the matching color
     * @param  resource $imageResource gd image resource
     * @param  array    $match         array('char code' => ['r','g','b']). Each colors of $imageResource will be replaced by the corresponding 'char code'
     * @return array                   return an array of array of chars
     */
    protected function imageCharArray($imageResource, $match = array(' '=>['r'=>255,'g'=>255,'b'=>255], 'o'=>['r'=>0,'g'=>0,'b'=>0])) {
      $return=array();

      for($y=0; $y<imagesy($imageResource); $y++) {
        for($x=0; $x<imagesx($imageResource); $x++) {
          $rgb=imagecolorat($imageResource,$x,$y);
          $r = ($rgb >> 16) & 0xFF;
          $g = ($rgb >> 8) & 0xFF;
          $b = $rgb         & 0xFF;

          foreach($match as $key=>$data) {
            if($r==$match[$key]['r'] && $g==$match[$key]['g'] && $b==$match[$key]['b'])
              $return[$y][$x]=$key;
          }
        }
      }
      return $return;
    }

    /**
     * Display a CharArray. It just print the $charArray. You must create this $charArray with imageCharArray()
     * @param  array $charArray An array of array of chars, generated with imageCharArray()
     */
    protected function displayCharArray($charArray) {
      echo '<pre>';
      for($i=0; $i<count($charArray); $i++) {
        for($j=0; $j<count($charArray[$i]); $j++) {
          if(isset($charArray[$i][$j]))
            print_r($charArray[$i][$j]);
        }
        echo "<br />";
      }
      echo '</pre>';
    }

    /**
     * Compare 2 charArrays and return the number of differences between those 2 arrays.
     * @param  array   $charArray1 An array of array of chars, generated with imageCharArray()
     * @param  array   $charArray2 An array of array of chars, generated with imageCharArray()
     * @return int                The number of differences between those 2 charArrays
     */
    protected function compareCharArray($charArray1,$charArray2) {
      $differences = 0;
      for($i=0; $i<count($charArray1); $i++)
      {
        for($j=0; $j<count($charArray1[$i]); $j++)
        {
          //we count any pixel difference between the 2 charArrays
          if(!isset($charArray2[$i][$j]) || $charArray1[$i][$j]!=$charArray2[$i][$j])
            $differences++;
        }
          //if charArray2 got more or less columns than charArray1 for the current line, we sum the difference 
          if(isset($charArray2[$i])) {
            $extra = count($charArray2[$i]) - count($charArray1[$i]);
            $differences += abs($extra);
          }
      }
      //if the total number of lines is different, we add this difference. It will be better to count each chars within those lines instead of the number of lines [TODO]
      $extra = count($charArray2) - count($charArray1);
        if($extra>0)
          $differences += $extra;

      return $differences;
    }

    /**
     * Compares $imageResource to images located in $knownImagesFolder. A match happens as long as $maxdifferences is not reached. It returns the first char of the "best" matching image filename. 
     * @param  resource $imageResource      gd image resource
     * @param  integer   $maxdifferences     maximum number of differences allowed to make a match. Default value is 10 
     * @param  string    $knownImagesFolder  location of known images, to be compared.
     * @param  boolean   $saveUnknown        TRUE to save unknown images in $knownImagesFolder location, or FALSE. Default value is FALSE
     * @return char                          the first letter of the best match image filename
     */
    protected function imageFind($imageResource, $maxdifferences = 10, $knownImagesFolder = '', $saveUnknown = FALSE) {
      $filelist = array();  //it will contains the list of images to be compared to $imageResource
      $bestMatch = $maxdifferences+1;

      if ($dir = @opendir($knownImagesFolder)) 
      {
        while (($file = readdir($dir)) !== false) 
          if(in_array(strtolower(substr($file, strripos($file,'.')+1)), ["jpg","png","jpeg","gif"]))
            $filelist[] = $file;
        closedir($dir);
        if(count($filelist)>0)
          sort($filelist);
      }

      $charArray = $this->imageCharArray($imageResource);
      for($i=0; $i<count($filelist); $i++)
      {
        $imageTemp = $this->imageCreateFrom($knownImagesFolder.$filelist[$i]);
        $arrayTemp = $this->imageCharArray($imageTemp);
        $differences = $this->compareCharArray($charArray, $arrayTemp);

        if($differences <= $maxdifferences)
        {
          if($this->verbose) {
            $this->displayCharArray($charArray); echo '<br />'."\r\n";
            echo "VS<br />";
            $this->displayCharArray($arrayTemp); echo '<br />'."\r\n";
            echo 'differences:'.$differences.'<br />'."\r\n";
            echo 'letter:'.$knownImagesFolder.$filelist[$i].'<br />'."\r\n";
          }

          $arrayMatch[$differences] = $filelist[$i][0]; //we take the first char of the filename
          if($differences < $bestMatch)
            $bestMatch = $differences;
        }
      }
      if(!isset($arrayMatch)) {
        $arrayMatch[$bestMatch]=$this->CONST_unknownChar;
      
        if($saveUnknown === TRUE)
          $this->imageWriteOnDisk($imageResource, $knownImagesFolder.$this->CONST_unknownChar.mt_rand().'.png');
      }
  
      return $arrayMatch[$bestMatch];
    }

    /**
     * Using the captcha image saved on the local server in the location: $workingDirectory."/".$filenameCaptcha,
     * This function will analyze it's content and return a string containing the text written in the captcha image.
     * @return string The text written in the captcha image previously downloaded on the server.
     */
    public abstract function solveCaptcha();

  }
?>