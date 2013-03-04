<?php
/**
 * Plugin Name: WP CodeMirror
 * Plugin URI: https://github.com/chrisguitarguy/WP-CodeMirror
 * Description: Remove TinyMCE in favor of CodeMirror.
 * Version: 1.0
 * Author: Christopher Davis
 * Author URI: http://christopherdavis.me
 * License: MIT
 *
 * Copyright (c) 2013 Christopher Davis <http://christopherdavis.me>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * @category    WordPress
 * @author      Christopher Davis <http://christopherdavis.me>
 * @copyright   2013 Christopher Davis
 * @license     http://opensource.org/licenses/MIT MIT
 */

!defined('ABSPATH') && exit;

WP_CodeMirror::init();

class WP_CodeMirror
{
    private static $ins = null;

    private $url;

    public static function init()
    {
        add_action('load-post.php', array(self::instance(), '_setup'));
        add_action('load-post-new.php', array(self::instance(), '_setup'));
    }

    public static function instance()
    {
        if (is_null(self::$ins)) {
            self::$ins = new self;
        }

        return self::$ins;
    }

    public function __construct()
    {
        $this->url = plugins_url('codemirror', __FILE__);
    }

    public function _setup()
    {
        $screen = get_current_screen();

        if (empty($screen->post_type) || !post_type_supports($screen->post_type, 'editor')) {
            return;
        }

        $user = wp_get_current_user();

        if (empty($user->rich_editing) || 'true' === $user->rich_editing) {
            return;
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_footer', array($this, 'setupCodemirror'), 99);
        add_filter('wp_default_editor', array($this, 'setDefaultEditor'));
        add_filter('quicktags_settings', array($this, 'removeQuickTags'), 10, 2);
    }

    public function enqueue()
    {
        wp_enqueue_script(
            'codemirror.js',
            $this->url . '/lib/codemirror.js',
            array(),
            '3.1'
        );

        $modes = array(
            'xml',
            'javascript',
            'css',
            'htmlmixed',
        );

        foreach ($modes as $mode) {
            wp_enqueue_script(
                "codemirror.{$mode}.js",
                $this->url . "/mode/{$mode}/{$mode}.js",
                array('codemirror.js'),
                '3.1'
            );
        }

        wp_enqueue_style(
            'codemirror.css',
            $this->url . '/lib/codemirror.css',
            array(),
            '3.1',
            'screen'
        );
    }

    public function setupCodemirror()
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var cnt = document.getElementById('content'), cm;

            if (!cnt) {
                return;
            }

            cm = CodeMirror.fromTextArea(cnt, {
                mode: 'htmlmixed',
                lineNumbers: true,
                theme: '<?php echo esc_js(apply_filters('wp_codemirror_theme', 'default')); ?>',
                indentUnit: <?php echo absint(apply_filters('wp_codemirror_indent_unit', 4)); ?>,
                lineWrapping: true
            });

            // what follows is a bunch of hacks to (hopefully) get this to play
            // nice with the media uploader. Since TinyMCE and QTags are not
            // present we need to see the wpActiveEditor global ourselves.
            $(cnt).parents('.wp-editor-wrap').on('click', function(e) {
                wpActiveEditor = cnt.id;
            });

            window.old_send_to_editor = window.send_to_editor;

            window.send_to_editor = function(res) {
                if (wpActiveEditor && cnt.id == wpActiveEditor) {
                    cm.replaceRange(res, cm.getCursor());
                } else {
                    return window.old_send_to_editor(res);
                }
            };
        });
        </script>
        <style type="text/css">#wp-word-count { display: none; }</style>
        <?php
    }

    public function setDefaultEditor($ed)
    {
        return 'html';
    }

    public function removeQuickTags($settings, $editor_id)
    {
        // quick tags don't play nice with code mirror...
        if ('content' === $editor_id) {
            return array('buttons' => ' ');
        }

        return $settings;
    }
}
