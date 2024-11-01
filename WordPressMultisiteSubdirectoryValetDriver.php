<?php

namespace Valet\Drivers\Custom;

use Valet\Drivers\BasicValetDriver;

class WordPressMultisiteSubdirectoryValetDriver extends BasicValetDriver
{
    public $wp_root = false; // "wp"

    /**
     * Determine if the driver serves the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
       // set wp-config.php path
        $wpConfig = $sitePath . '/wp-config.php';

        // if the usual path does not exist
        // check the parent directory instead
        if (!file_exists($wpConfig)) {
            $wpConfig = dirname($sitePath) . '/wp-config.php';
        }

        // Look for MULTISITE in wp-config.php. It should be there for multisite installs.
        return file_exists($wpConfig) &&
            (strpos( file_get_contents($wpConfig), 'MULTISITE') !== false) &&
            (
                //Double check if we are using subdomains.
                strpos( file_get_contents($wpConfig), "define('SUBDOMAIN_INSTALL',false)") ||
                strpos( file_get_contents($wpConfig), "define('SUBDOMAIN_INSTALL', false)") ||
                strpos( file_get_contents($wpConfig), "define( 'SUBDOMAIN_INSTALL', false )")
            );
    }

    /**
     * Get the fully resolved path to the application's front controller.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): string
    {
        $_SERVER['PHP_SELF']    = $uri;
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

        // If URI contains one of the main WordPress directories, and it's not a request for the Network Admin,
        // drop the subdirectory segment before routing the request
        if ( ( stripos($uri, 'wp-admin') !== false || stripos($uri, 'wp-content') !== false || stripos($uri, 'wp-includes') !== false ) ) {

            if ( stripos($uri, 'wp-admin/network') === false ) {
                $uri = substr($uri, stripos($uri, '/wp-') );
            }

            if ( $this->wp_root !== false && file_exists($sitePath . "/{$this->wp_root}/wp-admin") ) {
                $uri = "/{$this->wp_root}" . $uri;
            }
       	}

        // Handle wp-cron.php properly
        if ( stripos($uri, 'wp-cron.php') !== false ) {
            $new_uri = substr($uri, stripos($uri, '/wp-') );

            if ( file_exists( $sitePath . $new_uri ) ) {
                return $sitePath . $new_uri;
            }
        }

        return parent::frontControllerPath(
            $sitePath, $siteName, $this->forceTrailingSlash($uri)
        );
    }

    /**
     * Determine if the incoming request is for a static file.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)/*: string|false */
    {
    	// If the URI contains one of the main WordPress directories and it doesn't end with a slash,
    	// drop the subdirectory from the URI and check if the file exists. If it does, return the new uri.
        if ( stripos($uri, 'wp-admin') !== false || stripos($uri, 'wp-content') !== false || stripos($uri, 'wp-includes') !== false ) {
        	if ( substr($uri, -1, 1) == "/" ) return false;

       		$new_uri = substr($uri, stripos($uri, '/wp-') );

            if ( $this->wp_root !== false && file_exists($sitePath . "/{$this->wp_root}/wp-admin") ) {
                $new_uri = "/{$this->wp_root}" . $new_uri;
            }

       		if ( file_exists( $sitePath . $new_uri ) ) {
       			return $sitePath . $new_uri;
       		}
       	}

        return parent::isStaticFile( $sitePath, $siteName, $uri );
    }

    /**
     * Redirect to uri with trailing slash.
     */
    private function forceTrailingSlash(string $uri): string
    {
        if (substr($uri, -1 * strlen('/wp-admin')) == '/wp-admin') {
            header('Location: '.$uri.'/'); die;
        }
        return $uri;
    }
}
