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

        if (!$objInstagramModule)
        {
            return;
        }

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

                $instagramPost['timestamp'] = strtotime($instagramPost['timestamp']);

                $headline = strlen($instagramPost['caption']) < 25 ? $instagramPost['caption'] : substr($instagramPost['caption'], 0, 24) . ' ...';
                $headline = utf8_encode($headline);

                $teaser = $instagramPost['caption'];

                // Strip off english version from text (we always have new line with '_' or '-' in between the german and english lines)
                $arrMessage = explode(PHP_EOL, $teaser);
                $blnDelimiterFound = false;
                $arrMessage = array_filter($arrMessage, function ($strLine) use (&$blnDelimiterFound) {
                    if (in_array($strLine, ['_', '-']))
                    {
                        $blnDelimiterFound = true;
                    }

                    return !$blnDelimiterFound || 0 === strpos($strLine, '#');
                });
                $teaser = implode(PHP_EOL, $arrMessage);

                $teaser = $this->injectLinks($teaser);
                // Inject paragraphs (split on double line breaks)
                $teaser = preg_split('/\n{2}/', $teaser);
                $teaser = array_map(function ($paragraph) { return '<p>' . $paragraph . '</p>'; }, $teaser);
                $teaser = implode('', $teaser);
                $teaser = nl2br($teaser);
                $teaser = utf8_encode($teaser);

                // Import
                $objInstagramNewsModel = new InstagramNewsModel();
                $objInstagramNewsModel->pid = $objNewsArchive->id;
                $objInstagramNewsModel->published = true;
                $objInstagramNewsModel->headline = $headline;
                $objInstagramNewsModel->teaser = $teaser;
                $objInstagramNewsModel->date = $instagramPost['timestamp'];
                $objInstagramNewsModel->time = $instagramPost['timestamp'];
                $objInstagramNewsModel->source = 'external';
                $objInstagramNewsModel->url = $instagramPost['permalink'];
                $objInstagramNewsModel->target = true; // target="_blank"
                $objInstagramNewsModel->instagramId = $instagramPost['id'];
                $objInstagramNewsModel->instagramCaption = utf8_encode($instagramPost['caption']);
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

    /**
     * @param $txt
     * @return string
     */
    protected function injectLinks($txt)
    {
        // find links and surround with proper anchor tag
        preg_match_all('#[-a-zA-Z0-9@:%_+.~\#?&/=]{2,256}\.[a-z]{2,4}\b(/[-a-zA-Z0-9@:%_+.~\#?&/=]*)?#si', $txt, $links);
        if (is_array($links) && is_array($links[0]) && !empty($links[0]))
        {
            foreach ($links[0] as $link)
            {
                $linkNew = $link;
                if (strpos($linkNew, 'http') !== 0)
                {
                    $linkNew = 'https://' . $linkNew;
                }

                $linkNew = sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", $linkNew, $link);
                $txt = str_replace($link, $linkNew, $txt);
            }
        }

        // find hashtags and surround with proper anchor tag
        preg_match_all('/#\w+/u', $txt, $hashtags);
        if (is_array($hashtags) && is_array($hashtags[0]) && !empty($hashtags[0]))
        {
            $hashtags = array_unique($hashtags[0]);

            foreach ($hashtags as $i => $hashtag)
            {
                $link = sprintf("https://www.instagram.com/explore/tags/%s", substr($hashtag, 1));
                $hashtagNew = sprintf("<a href=\"%s\" target=\"_blank\" class=\"hashtag-link\">%s</a>", $link, $hashtag);
                $txt = preg_replace_callback("/$hashtag(\W|$)/", function($matches) use ($hashtagNew) {
                    return $hashtagNew . $matches[1];
                }, $txt);
            }
        }

        return $txt;
    }
}
