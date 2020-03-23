<?php

$GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram'] = str_replace(
    'cfg_instagramStoreFiles',
    'cfg_instagramStoreFiles,instagramNewsArchives',
    $GLOBALS['TL_DCA']['tl_module']['palettes']['cfg_instagram']
);

$GLOBALS['TL_DCA']['tl_module']['fields']['instagramNewsArchives'] = [
    'label' => $GLOBALS['TL_LANG']['tl_module']['instagramNewsArchives'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'options_callback' => array('tl_module_news', 'getNewsArchives'),
    'eval' => array('multiple' => true, 'mandatory' => true),
    'sql' => "blob NULL"
];
