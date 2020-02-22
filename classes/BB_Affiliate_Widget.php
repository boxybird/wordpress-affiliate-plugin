<?php

namespace BoxyBird\Classes;

use WP_Widget;

class BB_Affiliate_Widget extends WP_Widget
{
    public function __construct()
    {
        $widget_options = [
          'classname'   => 'bb_affiliate_widget',
          'description' => 'This is an Affiliate Widget',
        ];

        parent::__construct('bb_affiliate_widget', 'Affiliate Widget', $widget_options);

        add_action('wp_ajax_my_user_like', [$this, 'htmlOutput']);
        add_action('wp_ajax_nopriv_my_user_like', [$this, 'htmlOutput']);
    }

    public function htmlOutput()
    {
        extract($_POST['data']);

        $ebay     = new BB_Ebay_Rss($keywords, $camp_id, $cat_ids ?? []);
        $products = array_filter($ebay->fetch()['rss']['channel']['item']) ?? [];
        $products = array_slice($products, 0, 3); ?>
            <?php if (!empty($products)): ?>
                <ul>
                    <?php foreach ($products as $product): ?>
                        <li>
                            <?php echo $product['description']; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($link = end($products)['link'])): ?>
                    <div>
                        <a class="view-more-link" href="<?php echo $link; ?>" target="_blank">View More</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>    
        <?php

        die();
    }

    public function widget($args, $instance)
    {
        // Widget active
        if (empty($instance['active']) || !$instance['active']) {
            return;
        }

        // Verify fields
        if (empty(trim($instance['camp_id']))) {
            echo '<p>Please set Ebay Campaign ID</p>';

            return;
        }

        $title = apply_filters('widget_title', $instance['title']);
        $data  = json_encode($this->preparedVaribles($instance));

        echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title']; ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const resultsContainer = document.querySelector('#js-bb-ebay-wrapper');
                    const data = <?php echo $data; ?>

                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            data: data,
                            action : 'my_user_like'
                        }
                    }).done(function (res) {
                        resultsContainer.innerHTML = res
                    });
                });
            </script>

            <style>
                #js-bb-ebay-wrapper img {
                    display: block;
                }
                
                #js-bb-ebay-wrapper .view-more-link {
                    display: block;
                    width: 100%;
                    color: #ffffff;
                    padding: 7px 15px;
                    background-color: #00BF80;
                    text-align: center;

                }
            </style>

            <div id="js-bb-ebay-wrapper"></div>
        <?php

        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title      = $instance['title'] ?? '';
        $cat_ids    = $instance['cat_ids'] ?? '';
        $camp_id    = $instance['camp_id'] ?? '';
        $default_kw = $instance['default_kw'] ?? '';
        $active     = empty($instance['active']) ? 0 : 1; ?>
            <div>
                <p>Current only supports Ebay RSS Feed.</p>
                <hr>
                <span>Title:</span>
                <p>
                    <input 
                        type="text" 
                        id="<?php echo $this->get_field_id('title'); ?>" 
                        name="<?php echo $this->get_field_name('title'); ?>" 
                        value="<?php echo esc_attr($title); ?>" />
                </p>
                <hr>
                <span>Ebay Campaign ID (required):</span>
                <p>
                    <input 
                        type="text" 
                        id="<?php echo $this->get_field_id('camp_id'); ?>" 
                        name="<?php echo $this->get_field_name('camp_id'); ?>" 
                        value="<?php echo esc_attr($camp_id); ?>"
                        required />
                </p>
                <hr>
                <span>Ebay Category ID's (comma separated, max: 3):</span>
                <p>
                    <input 
                        type="text" 
                        id="<?php echo $this->get_field_id('cat_ids'); ?>" 
                        name="<?php echo $this->get_field_name('cat_ids'); ?>" 
                        value="<?php echo esc_attr($cat_ids); ?>" />
                </p>
                <hr>
                <span>Default Keyword</span>
                <p>
                    <input 
                        type="text" 
                        id="<?php echo $this->get_field_id('default_kw'); ?>" 
                        name="<?php echo $this->get_field_name('default_kw'); ?>" 
                        value="<?php echo esc_attr($default_kw); ?>" />
                </p>
                <hr>
                <span>Widget Active:</span>
                <p>
                    <input 
                        type="checkbox" 
                        id="<?php echo $this->get_field_id('active'); ?>" 
                        name="<?php echo $this->get_field_name('active'); ?>"
                        value="<?php echo empty($active) ? 'off' : 'on' ?>"
                        <?php echo empty($active) ? '' : 'checked' ?> />
                </p>
            </div>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance               = $old_instance;
        $instance['active']     = $new_instance['active'];
        $instance['title']      = strip_tags($new_instance['title']);
        $instance['camp_id']    = strip_tags(trim($new_instance['camp_id']));
        $instance['cat_ids']    = strip_tags(trim($new_instance['cat_ids']));
        $instance['default_kw'] = strip_tags(trim($new_instance['default_kw']));

        return $instance;
    }

    protected function preparedVaribles($instance)
    {
        global $post;

        $camp_id  = trim($instance['camp_id']);

        $cat_ids = array_filter(array_map('trim', explode(',', $instance['cat_ids'])));

        $keywords = wp_list_pluck(get_field('bb_ebay_rss_feed_keywords', $post->ID) ?? [], 'keyword');
        $keywords = !empty($keywords) ? $keywords : [trim($instance['default_kw'])];

        return [
            'cat_ids'  => $cat_ids,
            'camp_id'  => $camp_id,
            'keywords' => $keywords,
        ];
    }
}
