<?php
/**
 * Email Template Handler
 * 
 * This class handles loading and processing email templates
 * with variable replacement functionality
 */

class EmailTemplate {
    private $templatePath;
    private $baseUrl;
    private $hostedImageUrls;
    
    /**
     * Maps image filename => CID (used for inline-attachment mode).
     * Populated during loadTemplateForEmail() when mode is 'cid'.
     */
    private $cidMap = [];

    public function __construct($baseUrl = null) {
        $this->templatePath = __DIR__ . '/../templates/';
        $this->baseUrl = $baseUrl ?: $this->getBaseUrl();
        $this->initializeHostedImageUrls();
    }
    
    /**
     * Initialize hosted image URLs mapping
     * Update these URLs with your actual hosted image URLs from ImgBB
     */
    private function initializeHostedImageUrls() {
        $this->hostedImageUrls = [
            'RISE UP PATRIOTS CONFERENCE BACKGROUND.png' => 'https://i.ibb.co/1Yfd9LCs/Frame-4918-2x.png',
            'urni-logo-vertical.png' => 'https://i.ibb.co/4ZGL3kfQ/URNI-LOGO-VERTICAL-COLOURED-1.png',
            'RISE UP PATRIOTS CONFERENCE LOGO 1.png' => 'https://i.ibb.co/1Yfd9LCs/Frame-4918-2x.png',
            'RISE UP PATRIOTS CONFERENCE THEME LOGO.png' => 'https://i.ibb.co/1Yfd9LCs/Frame-4918-2x.png',
            'phone-icon.png' => 'https://i.ibb.co/B267syXV/Phone-ICON.png',
            'envelope.png' => 'https://i.ibb.co/nsVBmFvQ/Social-Icon.png',
            'location.png' => 'https://i.ibb.co/YzmnD36/Social-Icon-1.png',
            'facebook-icon.png' => 'https://i.ibb.co/N65cWjRZ/Facebook-Icon.png',
            'instagram.png' => 'https://i.ibb.co/5gnSV8ch/instagram.png',
            'whatsapp.png' => 'https://i.ibb.co/d4cWRPcM/Whatsapp-Icon.png',
            'tiktok.png' => 'https://i.ibb.co/Y48Nd1XT/Tiktok-Icon.png',
        ];
    }
    
    /**
     * Return the assets directory (two levels up from this file).
     */
    private function getAssetsDir() {
        return realpath(__DIR__ . '/../../assets');
    }

    /**
     * Load and process an email template
     * 
     * @param string $templateName Name of the template file (without .html extension)
     * @param array $variables Associative array of variables to replace
     * @return string Processed HTML content
     */
    public function loadTemplate($templateName, $variables = []) {
        $templateFile = $this->templatePath . $templateName . '.html';
        
        if (!file_exists($templateFile)) {
            error_log("Email template not found: " . $templateFile);
            throw new Exception("Email template not found: " . $templateFile);
        }
        
        $content = file_get_contents($templateFile);
        
        if ($content === false) {
            error_log("Failed to read email template: " . $templateFile);
            throw new Exception("Failed to read email template: " . $templateFile);
        }
        
        // Replace base URL
        $content = str_replace('{{BASE_URL}}', $this->baseUrl, $content);
        
        // Replace other variables
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . strtoupper($key) . '}}', htmlspecialchars($value), $content);
        }
        
        return $content;
    }
    
    /**
     * Get the base URL for the application
     * 
     * @return string Base URL
     */
    private function getBaseUrl() {
        // For email delivery, we need to use a publicly accessible URL
        // Check if we have environment variables for production
        if (isset($_ENV['APP_URL'])) {
            return rtrim($_ENV['APP_URL'], '/');
        }
        
        // For development/testing, you should replace this with your actual domain
        // when deploying to production or use a service like ngrok for testing
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        
        // If running on localhost, we need to use a publicly accessible URL
        // For now, we'll use a placeholder that you should replace with your actual domain
        if (strpos($host, 'localhost') !== false) {
            // TODO: Replace with your actual domain when deploying
            // For testing, you can use ngrok or similar service
            return 'https://your-domain.com'; // Replace with your actual domain
        }
        
        return $protocol . '://' . $host;
    }
    
    /**
     * Set custom base URL
     * 
     * @param string $url Base URL
     */
    public function setBaseUrl($url) {
        $this->baseUrl = rtrim($url, '/');
    }
    
    /**
     * Convert image to base64 data URL for email embedding
     * 
     * @param string $imagePath Path to the image file
     * @return string Base64 data URL or original path if conversion fails
     */
    private function imageToDataUrl($imagePath) {
        // If already an absolute external URL, return as-is (nothing to embed from disk)
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return $imagePath;
        }

        // Remove the base URL part to get the relative path
        $relativePath = str_replace($this->baseUrl . '/', '', $imagePath);
        
        // Construct the full file path
        $fullPath = __DIR__ . '/../../' . $relativePath;
        
        if (file_exists($fullPath)) {
            $imageData = file_get_contents($fullPath);
            $mimeType = mime_content_type($fullPath);
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        }
        
        return $imagePath; // Return original if file not found
    }
    
    /**
     * Convert local image path to hosted URL
     * 
     * @param string $imagePath Local image path
     * @return string Hosted image URL or original path if not found
     */
    private function getHostedImageUrl($imagePath) {
        // If already an absolute external URL, return as-is — no need to remap
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return $imagePath;
        }

        // Extract filename from the path
        $filename = basename($imagePath);
        
        // URL decode the filename to handle encoded characters
        $filename = urldecode($filename);
        
        // Check if we have a hosted URL for this image
        if (isset($this->hostedImageUrls[$filename])) {
            return $this->hostedImageUrls[$filename];
        }
        
        return $imagePath; // Return original if no hosted URL found
    }

    /**
     * Replace an image src with a cid: reference and record the mapping.
     * Works for both absolute https:// URLs (extracts filename) and local paths.
     * Handles filename mismatches where the URL uses hyphens but the local
     * file uses spaces (e.g. RISE-UP-PATRIOTS... vs RISE UP PATRIOTS...).
     *
     * @param string $imagePath  The original src value
     * @return string            The cid: reference to use in HTML
     */
    private function buildCidReference($imagePath) {
        $assetsDir = $this->getAssetsDir();

        // Derive filename from URL or path
        $rawFilename = urldecode(basename($imagePath));

        // Try multiple filename variations to handle hyphen/space mismatches
        $candidates = array_unique([
            $rawFilename,                                   // exact as-is
            str_replace('-', ' ', $rawFilename),            // hyphens → spaces
            str_replace(' ', '-', $rawFilename),            // spaces → hyphens
        ]);

        $localFile = null;
        $resolvedFilename = null;
        foreach ($candidates as $candidate) {
            $path = $assetsDir . DIRECTORY_SEPARATOR . $candidate;
            if (file_exists($path)) {
                $localFile = $path;
                $resolvedFilename = $candidate;
                break;
            }
        }

        if (!$localFile) {
            // Last resort: case-insensitive scan of the assets directory
            $files = scandir($assetsDir);
            $lowerRaw = strtolower($rawFilename);
            foreach ($files as $file) {
                if (strtolower($file) === $lowerRaw ||
                    strtolower(str_replace('-', ' ', $file)) === str_replace('-', ' ', $lowerRaw)) {
                    $localFile = $assetsDir . DIRECTORY_SEPARATOR . $file;
                    $resolvedFilename = $file;
                    break;
                }
            }
        }

        if (!$localFile) {
            error_log("EmailTemplate CID: local file not found for '{$rawFilename}', keeping original src.");
            return $imagePath;
        }

        // Build a safe CID (no spaces, no special chars)
        $cid = 'img_' . preg_replace('/[^a-z0-9]/i', '_', pathinfo($resolvedFilename, PATHINFO_FILENAME))
                      . '_' . md5($resolvedFilename)
                      . '@email';

        // Store mapping: cid => absolute local path (deduplicated by CID)
        $this->cidMap[$cid] = $localFile;

        return 'cid:' . $cid;
    }

    /**
     * After calling loadTemplateForEmail() with mode 'cid', call this method
     * to get all the images that must be attached via PHPMailer->addEmbeddedImage().
     *
     * @return array  [ cid => absolute_local_path, ... ]
     */
    public function getCidImages() {
        return $this->cidMap;
    }

    /**
     * Load template with different image handling options for email compatibility
     * 
     * @param string $templateName Name of the template file
     * @param array $variables Variables to replace
     * @param string $imageMode  'cid' (recommended), 'embedded' (base64), 'hosted', or 'local'
     * @return string Processed HTML content
     */
    public function loadTemplateForEmail($templateName, $variables = [], $imageMode = 'cid') {
        $this->cidMap = []; // Reset CID map on each call
        $content = $this->loadTemplate($templateName, $variables);
        
        if ($imageMode === 'cid') {
            // Replace every <img src="..."> with a cid: reference.
            // NOTE: Background images (CSS background-image / background="" attribute) are
            // intentionally LEFT AS-IS. Replacing them with cid: causes email clients like
            // Gmail to render the attached image as a visible inline image instead of a
            // background, because Gmail strips CSS background-image and the background=""
            // attribute entirely. Leaving them as absolute URLs is the correct behaviour:
            // clients that support CSS backgrounds (Outlook, Apple Mail) will render them,
            // while Gmail will simply skip them (showing the fallback background colour).
            $content = preg_replace_callback(
                '/src="([^"]*\.(png|jpg|jpeg|gif|svg))"/i',
                function($matches) {
                    return 'src="' . $this->buildCidReference($matches[1]) . '"';
                },
                $content
            );

        } elseif ($imageMode === 'embedded') {
            // Find all image sources and convert to data URLs
            $content = preg_replace_callback(
                '/src="([^"]*\.(png|jpg|jpeg|gif|svg))"/i',
                function($matches) {
                    return 'src="' . $this->imageToDataUrl($matches[1]) . '"';
                },
                $content
            );
            
            // Also handle background images
            $content = preg_replace_callback(
                '/background-image:\s*url\("([^"]*\.(png|jpg|jpeg|gif|svg))"\)/i',
                function($matches) {
                    return 'background-image: url("' . $this->imageToDataUrl($matches[1]) . '")';
                },
                $content
            );
        } elseif ($imageMode === 'hosted') {
            // Find all image sources and convert to hosted URLs
            $content = preg_replace_callback(
                '/src="([^"]*\.(png|jpg|jpeg|gif|svg))"/i',
                function($matches) {
                    return 'src="' . $this->getHostedImageUrl($matches[1]) . '"';
                },
                $content
            );
            
            // Also handle background images
            $content = preg_replace_callback(
                '/background-image:\s*url\("([^"]*\.(png|jpg|jpeg|gif|svg))"\)/i',
                function($matches) {
                    return 'background-image: url("' . $this->getHostedImageUrl($matches[1]) . '")';
                },
                $content
            );
        }
        // If imageMode is 'local', no conversion is needed
        
        return $content;
    }
    
    /**
     * Update hosted image URLs (call this method to update URLs after uploading to ImgBB)
     * 
     * @param array $urls Associative array of filename => hosted URL
     */
    public function updateHostedImageUrls($urls) {
        $this->hostedImageUrls = array_merge($this->hostedImageUrls, $urls);
    }
}
?>