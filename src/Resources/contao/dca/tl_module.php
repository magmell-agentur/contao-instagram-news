<?php

$GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram'] = str_replace(
    'cfg_instagramStoreFiles',
    'cfg_instagramStoreFiles,instagramNewsArchives,instagramUnpublished',
    $GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram']
);

$GLOBALS['TL_DCA']['tl_module']['fields']['instagramNewsArchives'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => array('tl_module_news', 'getNewsArchives'),
    'eval' => array('multiple' => true, 'mandatory' => true),
    'sql' => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['instagramUnpublished'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => array('tl_class'=>'w50'),
    'sql' => "char(1) NOT NULL default ''"
];
