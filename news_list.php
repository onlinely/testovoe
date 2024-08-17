<?
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Type\DateTime;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Context;
use Bitrix\Iblock\IblockTable;
?>

<?
if (!Loader::includeModule('iblock')) {
    return;
}

// Настройки для корректного вывода даты на русском
setlocale(LC_TIME, 'ru_RU.UTF-8');

// Параметры пагинации и лимита
$request = Context::getCurrent()->getRequest();
$pageSize = $request->getQuery('pageSize') ?: 10; // По умолчанию 10 записей
$page = $request->getQuery('page') ?: 1; // По умолчанию первая страница

// Настройка кэширование
$cacheTime = 86400; // 24 часа
$cacheId = 'news_list_' . $pageSize . '_' . $page;
$cacheDir = '/news_list_cache/';
$cache = Cache::createInstance();

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    $newsArray = $cache->getVars();
} elseif ($cache->startDataCache()) {

    $newsArray = [];

    $filter = [
        'IBLOCK_ID' => 12,
        'ACTIVE' => 'Y',
        '>=DATE_ACTIVE_FROM' => DateTime::createFromTimestamp(strtotime('01.01.2015 00:00:00')),
        '<=DATE_ACTIVE_FROM' => DateTime::createFromTimestamp(strtotime('31.12.2015 23:59:59')),
    ];

    $select = [
        'ID',
        'IBLOCK_ID',
        'IBLOCK_SECTION_ID',
        'NAME',
        'DETAIL_PAGE_URL',
        'DATE_ACTIVE_FROM',
        'PREVIEW_PICTURE',
    ];

    $elements = \CIBlockElement::GetList(
        ['DATE_ACTIVE_FROM' => 'ASC'],
        $filter,
        false,
        ['nPageSize' => $pageSize, 'iNumPage' => $page],
        $select
    );

    while ($element = $elements->GetNext()) {
        // Получаем название раздела
        $section = SectionTable::getList([
            'select' => ['NAME'],
            'filter' => ['ID' => $element['IBLOCK_SECTION_ID']],
        ])->fetch();

        // Получаем автора
        $authorProp = \CIBlockElement::GetProperty(4, $element['ID'], [], ['CODE' => 'AUTHOR'])->Fetch();
        $authorName = \CIBlockElement::GetByID($authorProp['VALUE'])->GetNext()['NAME'];

        // Если автора нет, устанавливаем пустую строку
        $authorName = $authorName ?: '';

        // Получаем ссылку на изображение
        $image = \CFile::GetPath($element['PREVIEW_PICTURE']);

        // Если изображения нет, устанавливаем пустую строку
        $image = $image ?: '';

        // Формируем URL
        $iblock = IblockTable::getList([
            'filter' => ['ID' => $element['IBLOCK_ID']],
            'select' => ['DETAIL_PAGE_URL']
        ])->fetch();

        $url = \CIBlock::ReplaceDetailUrl($iblock['DETAIL_PAGE_URL'], $element, true, 'E');

        // Форматируем дату
        $date = new DateTime($element['DATE_ACTIVE_FROM']);
        $formattedDate = strftime('%d %B %Y %H:%M', $date->getTimestamp());

        // Формируем массив
        $news[] = [
            'id' => $element['ID'],
            'url' => $url,
            'image' => $image,
            'name' => $element['NAME'],
            'sectionName' => $section['NAME'],
            'date' => $formattedDate,
            'author' => $authorName,
        ];
    }

    // Сохраняем в кэш
    $cache->endDataCache($news);
}

// Выводим результат
header('Content-Type: application/json');
echo json_encode($news, JSON_UNESCAPED_UNICODE);
?>