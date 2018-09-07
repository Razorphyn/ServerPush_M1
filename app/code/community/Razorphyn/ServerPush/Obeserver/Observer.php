<?php

/**
 * Plugin to add a Link header for each static asset
 */
class Razorphyn_ServerPush_Model_Observer
{
    protected $request;
    private $storeManager;
    private $appState;

    public function __construct() {
        $this->request = Mage::app()->getRequest();
        $this->storeManager = Mage::getBaseUrl();
        $this->appState = Mage::getDesign()->getArea();
    }

    /**
     * Intercept the sendResponse call
     */
    public function beforeSendResponse()
    {
		$response=Mage::app()->getResponse();
        if ($response instanceof Mage::app()->getResponse() && $this->shouldAddLinkHeader($response)) {
            $this->addLinkHeader($response);
        }
    }

    /**
     * Check if the headers needs to be sent.
     */
    protected function shouldAddLinkHeader($response)
    {
        if ($this->appState->getAreaCode() !== 'frontend') {
            return false;
        }

        if ($response->isRedirect()) {
            return false;
        }

        if ($this->request->isAjax()) {
            return false;
        }

        if (!$response->getContent()) {
            return false;
        }

        return true;
    }

    /**
     * Add Link header to the response, based on the content
     */
    protected function addLinkHeader($response)
    {
		$cookie=Mage::getModel('core/cookie')->get('serverpush');
		$cookie=($cookie==false)? null: json_decode($cookie);
		
        $values = [];
		$crawler = new DOMDocument();
		$crawler->loadHTML($response->getContent());
		$crawler->preserveWhiteSpace = false;

        // Find all stylesheets
        $stylesheets = $crawler->query('//link[@rel="stylesheet"][@href]');
		
		foreach ($images as $link) {
			$link = $this->prepareLink($link->getAttribute('href'));
            if (!empty($link)) {
				$push['l'][$link]=substr(md5_file($link), 0, 8);
				if(is_null($cookie) || (is_object($cookie) && !array_key_exists($link,$cookie['l']))){
					$values[] = "<".$link.">; rel=preload; as=style";
				}
            }
        }

        // Find all scripts
        $scripts = $crawler->query('//script[@type="text/javascript"][@src]');;
        foreach ($scripts as $link) {
			$link = $this->prepareLink($link->getAttribute('src'));
            if (!empty($link)) {
				$push['i'][$link]=substr(md5_file($link), 0, 8);
				if(is_null($cookie) || (!empty($cookie) && !array_key_exists($link,$cookie['i']))){
					$values[] = "<".$link.">; rel=preload; as=script";
				}
            }
        }

        // Find all images
        $images = $crawler->query('//img[@src]');
		
        foreach ($images as $link) {
			$link = $this->prepareLink($link->getAttribute('src'));
            if (!empty($link)) {
				$push['s'][$link]=substr(md5_file($link), 0, 8);
				if(is_null($cookie) || (!empty($cookie) && !array_key_exists($link,$cookie['s']))){
					$values[] = "<".$link.">; rel=preload; as=image";
				}
            }
        }

        if (count($push)>0) {
			Mage::getModel('core/cookie')->set('serverpush',json_encode($push), 60*60*24*30);
			if (count($values)>0)
				$response->setHeader('Link', implode(', ', $values));
        }
    }

    /**
     * Prepare and check the link
     */
    protected function prepareLink($link)
    {
        if (empty($link) || !is_string($link)) {
            return '';
        }

        // Absolute urls
        if ($link[0] === '/') {
            return $link;
        }

        // If it's not absolute, we only parse absolute urls
        $scheme = parse_url($link, PHP_URL_SCHEME);
        if ( ! in_array($scheme, ['http', 'https'])) {
            return '';
        }

        // Replace the baseUrl to save some chars.
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        if (strpos($link, $baseUrl) === 0) {
            $link = '/' . ltrim(substr($link, strlen($baseUrl)), '/');
        }

        return $link;
    }
}
