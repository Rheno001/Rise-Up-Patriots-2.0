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
            'RISE UP PATRIOTS CONFERENCE BACKGROUND.png' => 'https://i.ibb.co/qFsFSBwv/RISE-UP-PATRIOTS-CONFERENCE-BACKGROUND.png',
            'urni-logo-vertical.png' => 'https://i.ibb.co/YB45NGM0/urni-logo-vertical.png',
            'RISE UP PATRIOTS CONFERENCE LOGO 1.png' => 'https://i.ibb.co/mVBc6YQw/RISE-UP-PATRIOTS-CONFERENCE-LOGO-1.png',
            'RISE UP PATRIOTS CONFERENCE THEME LOGO.png' => 'https://i.ibb.co/bg3c3zxG/RISE-UP-PATRIOTS-CONFERENCE-THEME-LOGO.png',
            'phone-icon.png' => 'https://i.ibb.co/7xTT4g1c/phone-icon.png',
            'envelope.png' => 'https://i.ibb.co/bgVYfhq5/envelope.png',
            'location.png' => 'https://i.ibb.co/GQd3J9qd/location.png',
            'facebook-icon.png' => 'https://i.ibb.co/rKLyYrpK/facebook-icon.png',
            'instagram.png' => 'https://i.ibb.co/5gnSV8ch/instagram.png',
            'x.png' => 'https://i.ibb.co/S7PTxKWN/x.png',
            'whatsapp.png' => 'https://i.ibb.co/xKyYLD8t/whatsapp.png',
            'tiktok.png' => 'https://i.ibb.co/5ggpG43V/tiktok.png',
        ];
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
            throw new Exception("Email template not found: " . $templateFile);
        }
        
        $content = file_get_contents($templateFile);
        
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
     * Load template with different image handling options for email compatibility
     * 
     * @param string $templateName Name of the template file
     * @param array $variables Variables to replace
     * @param string $imageMode Image handling mode: 'hosted', 'embedded', or 'local'
     * @return string Processed HTML content
     */
    public function loadTemplateForEmail($templateName, $variables = [], $imageMode = 'hosted') {
        $content = $this->loadTemplate($templateName, $variables);
        
        if ($imageMode === 'embedded') {
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