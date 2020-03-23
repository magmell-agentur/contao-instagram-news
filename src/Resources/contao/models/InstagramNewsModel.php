<?php

namespace Contao;

/**
 * Class InstagramNewsModel
 * @package Contao
 *
 * @property int $instagramId
 * @property string $instagramCaption
 * @property string $instagramMediaType
 * @property string $instagramMediaUrl
 * @property string $instagramPermalink
 * @property string $instagramTstamp
 *
 */
class InstagramNewsModel extends NewsModel
{
    /**
     * @param int $instagramId
     * @param int $pid News archive id
     * @return Model\Collection|NewsModel|NewsModel[]|null
     */
    public static function findByInstagramIdAndPid($instagramId, $pid)
    {
        return static::findBy(['instagramId=?', 'pid=?'], [$instagramId, $pid]);
    }
}
