<?php
/**
 * Helper script to update hosted image URLs in EmailTemplate.php
 * 
 * After uploading images to ImgBB, update the URLs below and run this script
 * to automatically update the EmailTemplate.php file with the new URLs.
 */

// Update these URLs with your actual ImgBB URLs after uploading
$hostedImageUrls = [
    'RISE UP PATRIOTS CONFERENCE BACKGROUND.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/background.png',
    'urni-logo-vertical.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/urni-logo-vertical.png',
    'RISE UP PATRIOTS CONFERENCE LOGO 1.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/logo1.png',
    'RISE UP PATRIOTS CONFERENCE THEME LOGO.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/theme-logo.png',
    'phone-icon.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/phone-icon.png',
    'envelope.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/envelope.png',
    'location.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/location.png',
    'facebook-icon.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/facebook-icon.png',
    'instagram.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/instagram.png',
    'x.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/x.png',
    'whatsapp.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/whatsapp.png',
    'tiktok.png' => 'https://i.ibb.co/YOUR_IMAGE_ID/tiktok.png',
];

// Function to update EmailTemplate.php with new URLs
function updateEmailTemplateUrls($urls) {
    $templateFile = __DIR__ . '/EmailTemplate.php';
    
    if (!file_exists($templateFile)) {
        echo "Error: EmailTemplate.php not found!\n";
        return false;
    }
    
    $content = file_get_contents($templateFile);
    
    // Build the new array string
    $arrayString = "        \$this->hostedImageUrls = [\n";
    foreach ($urls as $filename => $url) {
        $arrayString .= "            '" . addslashes($filename) . "' => '" . addslashes($url) . "',\n";
    }
    $arrayString .= "        ];";
    
    // Replace the existing array in the file
    $pattern = '/\$this->hostedImageUrls = \[.*?\];/s';
    $content = preg_replace($pattern, $arrayString, $content);
    
    if (file_put_contents($templateFile, $content)) {
        echo "Successfully updated EmailTemplate.php with new hosted image URLs!\n";
        return true;
    } else {
        echo "Error: Could not write to EmailTemplate.php\n";
        return false;
    }
}

// Check if this script is being run directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "Updating hosted image URLs...\n";
    
    // Check if URLs have been updated
    $hasRealUrls = false;
    foreach ($hostedImageUrls as $url) {
        if (strpos($url, 'YOUR_IMAGE_ID') === false) {
            $hasRealUrls = true;
            break;
        }
    }
    
    if (!$hasRealUrls) {
        echo "Warning: Please update the URLs in this script with your actual ImgBB URLs before running!\n";
        echo "Replace 'YOUR_IMAGE_ID' with the actual image IDs from ImgBB.\n";
        exit(1);
    }
    
    if (updateEmailTemplateUrls($hostedImageUrls)) {
        echo "Done! Your email template will now use the hosted images.\n";
    }
}
?>