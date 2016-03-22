<?php

namespace Yab\Crawler;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler
{
    protected $url;
    protected $links;
    protected $maxDepth;

    public function __construct()
    {
        $this->baseUrl = '';
        $this->links = [];
        $this->depth = 0;
    }

    public function crawl($url, $maxDepth = 10)
    {
        $this->baseUrl = $url;
        $this->depth = $maxDepth;

        $this->spider($this->baseUrl, $maxDepth);

        return $this;
    }

    public function links()
    {
        return $this->links;
    }

    private function spider($url, $maxDepth)
    {
        try {

            $this->links[$url] = [
                'status_code' => 0,
                'url' => $url,
                'visited' => false,
                'is_external' => false,
            ];

            $client = new Client();
            $crawler = $client->request('GET', $url);

            $html = $crawler->getBody()->getContents();
            $statusCode = $crawler->getStatusCode();

            $this->links[$url]['status_code'] = $statusCode;
            if ($statusCode == 200) {
                $contentType = $crawler->getHeader('Content-Type');

                // Make sure the page is html
                if (strpos($contentType[0], 'text/html') !== false) {

                    // look at links within the page
                    $pageLinks = [];
                    if (@$this->links[$url]['is_external'] == false) {
                        $pageLinks = $this->extractLinks($html, $url);
                    }

                    // mark current url as visited
                    $this->links[$url]['visited'] = true;
                    // spawn spiders for the child links, marking the depth as decreasing
                    $this->spawn($pageLinks, $maxDepth - 1);
                }
            }
        } catch(\GuzzleHttp\Exception\RequestException $ex)  {
            // nothing
        } catch (Exception $ex) {
            $this->links[$url]['status_code'] = '404';
        }
    }

    private function spawn($links, $maxDepth)
    {
        // if we hit the max - then its the end of the rope
        if ($maxDepth == 0) {
            return;
        }

        foreach ($links as $url => $info) {
            // only pay attention to those we do not know
            if (! isset($this->links[$url])) {
                $this->links[$url] = $info;
                // we really only care about links which belong to this domain
                if (! empty($url) && ! $this->links[$url]['visited'] && ! $this->links[$url]['is_external']) {
                    $this->spider($this->links[$url]['url'], $maxDepth);
                }
            }
        }
    }

    private function checkIfExternal($url)
    {
        $baseUrl = str_replace(['http://', 'https://'], '', $this->baseUrl);

        if (preg_match("@http(s)?\://$baseUrl@", $url)) {
            return false;
        }

        return true;
    }

    private function extractLinks($html, $url)
    {
        $dom = new DomCrawler($html);

        $currentLinks = [];

        $dom->filter('a')->each(function(DomCrawler $node, $i) use (&$currentLinks) {
            // get the href
            $nodeUrl = $node->attr('href');

            // If we don't have it lets collect it
            if (! isset($this->links[$nodeUrl])) {

                $currentLinks[$nodeUrl]['is_external'] = false;
                $currentLinks[$nodeUrl]['url'] = $nodeUrl;
                $currentLinks[$nodeUrl]['visited'] = false;

                // check if the link is external
                if ($this->checkIfExternal($currentLinks[$nodeUrl]['url'])) {
                    $currentLinks[$nodeUrl]['is_external'] = true;
                }
            }
        });

        // if page is linked to itself, ex. homepage
        if (isset($currentLinks[$url])) {
            // let's avoid endless cylces
            $currentLinks[$url]['visited'] = true;
        }

        return $currentLinks;
    }
}