<?php
/**
 * Основные параметры WordPress.
 *
 * Скрипт для создания wp-config.php использует этот файл в процессе
 * установки. Необязательно использовать веб-интерфейс, можно
 * скопировать файл в "wp-config.php" и заполнить значения вручную.
 *
 * Этот файл содержит следующие параметры:
 *
 * * Настройки MySQL
 * * Секретные ключи
 * * Префикс таблиц базы данных
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** Параметры MySQL: Эту информацию можно получить у вашего хостинг-провайдера ** //
/** Имя базы данных для WordPress */
define('DB_NAME', 'tbsnodra_wordpress');

/** Имя пользователя MySQL */
define('DB_USER', 'tbsnodra_root');

/** Пароль к базе данных MySQL */
define('DB_PASSWORD', '1234554321');

/** Имя сервера MySQL */
define('DB_HOST', 'localhost');

/** Кодировка базы данных для создания таблиц. */
define('DB_CHARSET', 'utf8mb4');

/** Схема сопоставления. Не меняйте, если не уверены. */
define('DB_COLLATE', '');

/**#@+
 * Уникальные ключи и соли для аутентификации.
 *
 * Смените значение каждой константы на уникальную фразу.
 * Можно сгенерировать их с помощью {@link https://api.wordpress.org/secret-key/1.1/salt/ сервиса ключей на WordPress.org}
 * Можно изменить их, чтобы сделать существующие файлы cookies недействительными. Пользователям потребуется авторизоваться снова.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '8m>4{?S>{|TN:zHyoz4X%$?H{.7jNPhA/EruS$wK^0}_]f57r*02wI#cM-Cg)^TH');
define('SECURE_AUTH_KEY',  'J-97$?8sA r[jEzM9]bS/7J6cD?DV,6|naQwQkDHexXp;f>b+3EJ`lOeS);V9;7I');
define('LOGGED_IN_KEY',    'u%O@@h|,=y#Idv)~P*%FxXByVs5L*M8+VP[k 2X#S&0{,and8UeWS<ZYr^RhLwm^');
define('NONCE_KEY',        'p!D9xiR|N.*gJZ+:VhPC3H8-m%g$QRyK`PvFTVm)6{!Y{g2I.?HbDKEwTs<no+EU');
define('AUTH_SALT',        '4>hbz-k27ftg]3-O^Q=S8Q2PJ5<2SCHN-$n9Rp.~}FV.rZiKon=&[#pU2|dv#Ozs');
define('SECURE_AUTH_SALT', '-*Z*}?7r=E B>C/ECXxbvB%}0@Pj}I&Rt(2Qx*T.o:>mTyfo:W!Xe{q3;`q,=eul');
define('LOGGED_IN_SALT',   ' %HPB5dg(f5o&n@ndR FxW o;pd(R|plT=@JK1>Fwbf?!a<z?)] 58Py]BI|bcDD');
define('NONCE_SALT',       'rwAH]Y@Za@*`3)[`DF,0B9D<`.[8mkcA72{t0Vn*qxW:U>Ru=>>%6|?%F@McuCXM');

/**#@-*/

/**
 * Префикс таблиц в базе данных WordPress.
 *
 * Можно установить несколько сайтов в одну базу данных, если использовать
 * разные префиксы. Пожалуйста, указывайте только цифры, буквы и знак подчеркивания.
 */
$table_prefix  = 'wp_';

/**
 * Для разработчиков: Режим отладки WordPress.
 *
 * Измените это значение на true, чтобы включить отображение уведомлений при разработке.
 * Разработчикам плагинов и тем настоятельно рекомендуется использовать WP_DEBUG
 * в своём рабочем окружении.
 * 
 * Информацию о других отладочных константах можно найти в Кодексе.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* Это всё, дальше не редактируем. Успехов! */

/** Абсолютный путь к директории WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Инициализирует переменные WordPress и подключает файлы. */
require_once(ABSPATH . 'wp-settings.php');
