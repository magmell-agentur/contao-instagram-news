<?php

namespace Magmell\Contao\InstagramNews;

use Codefog\InstagramBundle\InstagramClient;
use Contao\InstagramNewsModel;
use Contao\ModuleModel;
use Contao\NewsArchiveModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Class InstagramNewsImporter
 * @package Magmell\Contao\InstagramNews
 */
class InstagramNewsImporter
{
    /**
     * @var InstagramClient
     */
    protected $client;

    /**
     * InstagramNewsImporter constructor.
     */
    public function __construct()
    {
        $this->client = System::getContainer()->get(InstagramClient::class);
    }

    public function run()
    {
        $objInstagramModule = ModuleModel::findByType('cfg_instagram');

        while($objInstagramModule->next())
        {
            if (!$objInstagramModule->cfg_instagramAccessToken
                || 0 === count($instagramPosts = $this->getFeedItems($objInstagramModule))
                || !$objInstagramModule->instagramNewsArchives
                || empty(unserialize($objInstagramModule->instagramNewsArchives))
            ) {
                continue;
            }

            $this->import($instagramPosts, unserialize($objInstagramModule->instagramNewsArchives));
        }
    }

    /**
     * Get the feed items from Instagram.
     *
     * @param $objModule
     * @return array
     */
    protected function getFeedItems($objModule): array
    {
        $response = $this->client->getMediaData($objModule->cfg_instagramAccessToken, (int) $objModule->id, false);

        if (null === $response) {
            return [];
        }

        $data = $response['data'];

        // Store the files locally
        if ($objModule->cfg_instagramStoreFiles) {
            $data = $this->client->storeMediaFiles($objModule->cfg_instagramStoreFolder, $data);
        }

        // Limit the number of items
        if ($objModule->numberOfItems > 0) {
            $data = array_slice($data, 0, $objModule->numberOfItems);
        }

        return $data;
    }

    /**
     * Import Instagram posts as tl_news.
     *
     * @param array $instagramPosts Array of posts to import
     * @param array $newsArchives Array of news archives ids to import posts to
     */
    protected function import($instagramPosts, $newsArchives)
    {
        if (!is_array($newsArchives) || !is_array($instagramPosts))
        {
            return;
        }

        foreach ($newsArchives as $intNewsArchiveId)
        {
            $objNewsArchive = NewsArchiveModel::findByPk($intNewsArchiveId);
            if (!$objNewsArchive)
            {
                continue; // News archive does not exist
            }

            foreach ($instagramPosts as $instagramPost)
            {
                $objInstagramNews = InstagramNewsModel::findByInstagramIdAndPid($instagramPost['id'], $intNewsArchiveId);
                if ($objInstagramNews && $objInstagramNews->count())
                {
                    continue; // Instagram post already imported
                }

                $instagramPost['caption'] = $this->removeEmoticons($instagramPost['caption']);
                $instagramPost['timestamp'] = strtotime($instagramPost['timestamp']);

                $headline = strlen($instagramPost['caption']) < 25 ? $instagramPost['caption'] : substr($instagramPost['caption'], 0, 24) . ' ...';
                $headline = utf8_encode($headline);

                $teaser = $instagramPost['caption'];
                // Inject paragraphs (split on double line breaks)
                $teaser = preg_split('/\n{2}/', $teaser);
                $teaser = array_map(function ($paragraph) { return '<p>' . $paragraph . '</p>'; }, $teaser);
                $teaser = implode('', $teaser);
                $teaser = nl2br($teaser);
                $teaser = utf8_encode($teaser);

                // Import
                $objInstagramNewsModel = new InstagramNewsModel();
                $objInstagramNewsModel->pid = $objNewsArchive->id;
                $objInstagramNewsModel->headline = $headline;
                $objInstagramNewsModel->teaser = $teaser;
                $objInstagramNewsModel->date = $instagramPost['timestamp'];
                $objInstagramNewsModel->time = $instagramPost['timestamp'];
                $objInstagramNewsModel->instagramId = $instagramPost['id'];
                $objInstagramNewsModel->instagramCaption = $instagramPost['caption'];
                $objInstagramNewsModel->instagramMediaType = $instagramPost['media_type'];
                $objInstagramNewsModel->instagramMediaUrl = $instagramPost['media_url'];
                $objInstagramNewsModel->instagramPermalink = $instagramPost['permalink'];
                $objInstagramNewsModel->instagramTstamp = $instagramPost['timestamp'];
                if ($instagramPost['contao']['uuid']) {
                    $objInstagramNewsModel->addImage = true;
                    $objInstagramNewsModel->singleSRC = StringUtil::uuidToBin($instagramPost['contao']['uuid']);
                }
                $objInstagramNewsModel->save();
            }
        }
    }

    protected function removeEmoticons($string)
    {
        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clean_text = preg_replace($regexEmoticons, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clean_text = preg_replace($regexSymbols, '', $clean_text);

        // Match Transport And Map Symbols
        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clean_text = preg_replace($regexTransport, '', $clean_text);

        // Match Miscellaneous Symbols
        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        $clean_text = preg_replace($regexMisc, '', $clean_text);

        // Match Dingbats
        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $clean_text = preg_replace($regexDingbats, '', $clean_text);

        return $clean_text;
    }
}
