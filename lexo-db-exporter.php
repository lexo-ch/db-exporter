<?php

/**
 * Plugin Name: DB Exporter
 * Plugin URI: https://github.com/lexo-ch/db-exporter
 * Description: Export database.
 * Author: LEXO
 * Version: 1.0.3
 * Author URI: https://www.lexo.ch
 */

!defined('WPINC') && die;

final class LexoDbExporter
{
    private const DB_EXPORTER_SLUG  = 'lexo-db-exporter';
    private const NONCE             = 'ldbe-tool';
    private const MIN_PHP_VERSION   = '7.4.1';
    private const HOOK_SLUG         = 'ldbe';

    private $file;
    private $path;
    private $url;
    private $name;
    private $permission;
    
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'setGeneralVars']);
        add_action('admin_menu', [$this, 'addSettingsPage'], 100);
        add_action('admin_enqueue_scripts', [$this, 'includeScripts']);
        add_action('wp_ajax_exportDatabase', [$this, 'exportDatabase']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'actionLinks']);
    }

    public function actionLinks($links)
    {
        $url = esc_url(
            add_query_arg(
                'page',
                self::DB_EXPORTER_SLUG,
                admin_url('admin.php')
            )
        );

        $settings_link = "<a href='$url'>" . __('Settings', 'ldbe') . '</a>';

        array_push(
            $links,
            $settings_link
        );

        return $links;
    }

    public function exportDatabase()
    {
        if (!check_ajax_referer(self::NONCE, 'security', false)) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'ldbe')
            ]);
        }

        if (empty($_POST['ldbe-old-string']) || empty($_POST['ldbe-new-string'])) {
            wp_send_json_error([
                'message' => __('All fields are required!', 'ldbe')
            ]);
        }

        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('Only logged users can perform this action!', 'ldbe')
            ]);
        }

        if (!current_user_can($this->permission)) {
            wp_send_json_error([
                'message' => __('You don\'t have enought permissions to perform this action!', 'ldbe')
            ]);
        }

        $old_string = sanitize_text_field($_POST['ldbe-old-string']);
        $new_string = sanitize_text_field($_POST['ldbe-new-string']);

        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

        if ($mysqli->connect_error) {
            die('Connection Error: ' . $mysqli->connect_error);
        }

        $mysqli->select_db(DB_NAME); 
        $mysqli->query("SET NAMES 'utf8'");

        $queryTables    = $mysqli->query('SHOW TABLES'); 

        while ($row = $queryTables->fetch_row()) {
            $target_tables[] = $row[0];
        }

        $content = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET time_zone = \"+00:00\";\r\n\r\n\r\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\r\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\r\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\r\n/*!40101 SET NAMES utf8 */;\r\n--\r\n-- Database: `" . DB_NAME . "`\r\n--\r\n\r\n\r\n";

        foreach ($target_tables as $table) {
            $content .= "\n\nDROP TABLE IF EXISTS `$table`;";

            $result         = $mysqli->query('SELECT * FROM '.$table);
            $fields_amount  = $result->field_count;
            $rows_num       = $mysqli->affected_rows;
            $res            = $mysqli->query('SHOW CREATE TABLE '.$table);
            $TableMLine     = $res->fetch_row();

            $content .= "\n".$TableMLine[1].";";

            for ($i = 0, $st_counter = 0; $i < $fields_amount; $i++, $st_counter=0) {
                while ($row = $result->fetch_row()) { //when started (and every after 100 command cycle):
                    if ($st_counter % 100 == 0 || $st_counter == 0) {
                        $content .= "\n\nINSERT INTO ".$table." VALUES";
                    }

                    $content .= "\n(";
                    
                    for($j=0; $j<$fields_amount; $j++) {
                        $row[$j] = str_replace("\n","\\n", addslashes($row[$j]));

                        if (isset($row[$j])) {
                            $content .= '"'.$row[$j].'"';
                        } else {
                            $content .= '""';
                        }

                        if ($j<($fields_amount-1)) {
                            $content.= ',';
                        }
                    }

                    $content .=")";

                    if ((($st_counter + 1) % 100 == 0 && $st_counter!=0) || $st_counter + 1 == $rows_num) {
                        $content .= ";";
                    } else {
                        $content .= ",";
                    }

                    $st_counter=$st_counter+1;
                }
            } $content .="\n";
    
            $content .= "\r\n\r\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\r\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\r\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
        
            $content = $this->searchAndReplace($old_string, $new_string, $content);
        }

        do_action(self::HOOK_SLUG . '/exported');

        wp_send_json_success([
            'message' => $content,
            'filename' => 'db-export_' . sanitize_title(get_site_url()) . '.sql'
        ]);
    }

    private function searchAndReplace($search, $replace, $content)
    {
        $content = str_replace($search, $replace, $content);

        $serstrings = preg_split("/(?<=[{;])s:/", $content);
    
        foreach ($serstrings as $i => $serstring) {
            if (!!strpos($serstring, $replace)) {
                $justString = @str_replace("\\", "", str_replace("\\\\", "j",explode('\\";', explode(':\\"', $serstring)[1])[0]));
                $correct = strlen($justString);
                $serstrings[$i] = preg_replace('/^\d+/', $correct, $serstrings[$i]);
            } 
        }

        $content = implode("s:", $serstrings);
        
        return $content;
    }

    public function setGeneralVars()
    {
        $this->file = __FILE__;
        $this->path = plugin_dir_path($this->file);
        $this->url  = plugin_dir_url($this->file);
        $this->name = get_file_data($this->file, [
            'Plugin Name' => 'Plugin Name'
        ])['Plugin Name'];

        $permission = apply_filters(self::HOOK_SLUG . '/permission', 'administrator');

        $this->permission = $permission;

        if (!class_exists('mysqli')) {
            add_action('admin_notices', [$this, 'mysqliMissing']);
        }

        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'requiredPhpVersion']);
        }
    }

    public function requiredPhpVersion()
    {
        ob_start(); ?>
            <div class="notice notice-error">
                <p><?php echo sprintf(
                    __('<code>%s</code> requires PHP version <code>>=%s</code>.', 'ldbe'),
                    $this->name,
                    self::MIN_PHP_VERSION
                ); ?></p>
            </div>
        <?php echo ob_get_clean();
    }

    public function mysqliMissing()
    {
        ob_start(); ?>
            <div class="notice notice-error">
                <p><?php echo sprintf(
                    __('<code>%s</code> requires <code>mysqli</code> in order to work.', 'ldbe'),
                    $this->name
                ); ?></p>
            </div>
        <?php echo ob_get_clean();
    }

    public function includeScripts()
    {
        wp_enqueue_style(
            self::DB_EXPORTER_SLUG,
            "{$this->url}css/style.css",
            false,
            md5_file("{$this->path}css/style.css"),
            'all'
        );

        wp_enqueue_script(
            self::DB_EXPORTER_SLUG,
            "{$this->url}js/scripts.js",
            ['jquery'],
            md5_file("{$this->path}js/scripts.js"),
            true
        );

        wp_localize_script(self::DB_EXPORTER_SLUG, 'ldbe_translations',
            [
                'confirmation_message' => __('All fields are required!', 'ldbe'),
            ]
        );
    }

    public function addSettingsPage()
    {
        add_menu_page(
            __('LEXO DB Exporter', 'ldbe'),
            __('LEXO DB Exporter', 'ldbe'),
            $this->permission,
            self::DB_EXPORTER_SLUG,
            [$this, 'settingsPageContent'],
            'dashicons-database-import'
        );
    }

    public function settingsPageContent()
    {
        ob_start(); ?>

            <div class="wrap lexo-db-export">
                <h1 class="wp-heading-inline">
                    <?php echo __('LEXO DB Exporter', 'ldbe'); ?>
                </h1>

                <div id="lexo-db-export-wrapper">
                    <p><?php echo __('Enter the new domain name and export the database used on this website.', 'ldbe'); ?></p>
                    
                    <label class="ldbe-label">
                        <span><?php echo __('Search for', 'ldbe'); ?></span>
                        <input
                            type="text"
                            class="ldbe-input"
                            id="ldbe-old-string"
                            value="//<?php echo preg_replace("(^https?://)", "", get_site_url()); ?>"
                        >
                    </label>

                    <label class="ldbe-label">
                        <span><?php echo __('Replace with', 'ldbe'); ?></span>
                        <input
                            type="text"
                            class="ldbe-input"
                            id="ldbe-new-string"
                            value=""
                        >
                    </label>

                    <div class="ip-box-tool-section">
                        <button
                            id="ldbe-submit"
                            class="button button-primary"
                            data-nonce="<?php echo wp_create_nonce(self::NONCE); ?>"
                        >
                            <?php echo __('Export DB', 'ldbe'); ?>
                        </button>
                        <span id="ldbe-submit-spinner" class="dashicons dashicons-update spin"></span>
                    </div>
                </div>
            </div>

        <?php echo ob_get_clean();
    }
}

new LexoDbExporter();