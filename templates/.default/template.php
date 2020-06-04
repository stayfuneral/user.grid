<?php

defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\UI\Filter\Options;
use Bitrix\Bizproc\Service\User;
use Bitrix\Main\UserTable;

$this->SetViewTarget('inside_pagetitle');

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', $arResult['FILTER_OPTIONS']);


$this->EndViewTarget();



$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', $arResult['GRID_OPTIONS']);
