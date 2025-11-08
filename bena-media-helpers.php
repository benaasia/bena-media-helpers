<?php
/**
 * Plugin Name: Bena Media Helpers
 * Description: Media utilities for WordPress: save external images, delete attachments with posts, and convert JPG/PNG uploads to WebP with per-feature toggles.
 * Version: 1.3.0
 * Author: Bena
 * Text Domain: bena-media-helpers
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bena_Media_Helpers {
    const OPTION_KEY = 'bena_media_helpers_options';
    const PAGE_SLUG  = 'bena-media-helpers';
    const VERSION    = '1.2.0';

    private $bena_options = [];

    public function __construct() {
        $this->bena_options = wp_parse_args( get_option( self::OPTION_KEY, [] ), self::bena_defaults() );

        add_action( 'plugins_loaded', [ $this, 'bena_load_textdomain' ] );
        add_action( 'admin_menu', [ $this, 'bena_register_menu' ] );
        add_action( 'admin_init', [ $this, 'bena_register_settings' ] );
        add_action( 'admin_head', [ $this, 'bena_admin_styles' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'bena_enqueue_assets' ] );

        if ( $this->bena_options['enable_remote_images'] ) {
            add_filter( 'content_save_pre', [ $this, 'bena_handle_remote_images' ] );
        }

        if ( $this->bena_options['enable_delete_attachments'] ) {
            add_action( 'before_delete_post', [ $this, 'bena_delete_post_attachments' ] );
        }

        add_filter( 'wp_handle_upload', [ $this, 'bena_process_upload' ], 8 );
        if ( $this->bena_options['enable_convert_webp'] ) {
            add_filter( 'wp_handle_upload', [ $this, 'bena_convert_upload_to_webp' ], 12 );
        }
    }

    public function bena_load_textdomain(): void {
        load_plugin_textdomain( 'bena-media-helpers', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    public function bena_render_feature_toggles(): void {
        $bena_groups = [
            'bena-core-features' => [
                'title' => __( 'Post Image Settings', 'bena-media-helpers' ),
                'items' => [
                    'enable_remote_images'     => __( 'Automatically save external images to the media library', 'bena-media-helpers' ),
                    'enable_filename_prefix'   => [
                        'label'        => __( 'Add a filename prefix', 'bena-media-helpers' ),
                        'append_field' => 'filename_prefix',
                    ],
                    'enable_delete_attachments' => __( 'Delete attachments when a post is deleted', 'bena-media-helpers' ),
                    'enable_convert_webp'       => [
                        'label'        => __( 'Convert JPG/PNG uploads to WebP', 'bena-media-helpers' ),
                        'append_field' => 'webp_quality',
                    ],
                ],
            ],
            'bena-watermark-features' => [
                'title'       => __( 'Watermark', 'bena-media-helpers' ),
                'description' => __( 'Choose a watermark position', 'bena-media-helpers' ),
                'exclusive'   => true,
                'items'       => [
                    'disable_watermark'     => [
                        'label'  => __( 'Disable watermark', 'bena-media-helpers' ),
                        'target' => 'none',
                    ],
                    'enable_watermark'      => [
                        'label'        => __( 'Watermark with a PNG image', 'bena-media-helpers' ),
                        'target'       => 'image',
                        'append_view'  => 'watermark_image',
                    ],
                    'enable_watermark_text' => [
                        'label'        => __( 'Watermark with text', 'bena-media-helpers' ),
                        'target'       => 'text',
                        'append_view'  => 'watermark_text',
                    ],
                ],
            ],
        ];

        $bena_watermark_toggle = $this->bena_options['enable_watermark'] ? 'enable_watermark' : ( $this->bena_options['enable_watermark_text'] ? 'enable_watermark_text' : 'disable_watermark' );
        ?>
        <div class="bena-feature-grid" role="group" aria-label="<?php esc_attr_e( 'Bena Media Feature Grid', 'bena-media-helpers' ); ?>">
            <?php foreach ( $bena_groups as $bena_group_key => $bena_group ) :
                $bena_items         = $bena_group['items'] ?? [];
                $bena_header_toggle = null;

                if ( ! empty( $bena_group['exclusive'] ) && isset( $bena_items['disable_watermark'] ) ) {
                    $bena_header_toggle = $bena_items['disable_watermark'];
                    unset( $bena_items['disable_watermark'] );
                }

                $bena_toggles_class = 'bena-feature-card__toggles';
                if ( 'bena-watermark-features' === $bena_group_key ) {
                    $bena_toggles_class .= ' bena-feature-card__toggles--watermark';
                }
                ?>
                <div class="bena-feature-card" id="<?php echo esc_attr( $bena_group_key ); ?>">
                    <div class="bena-feature-card__header">
                        <h3><?php echo esc_html( $bena_group['title'] ); ?></h3>

                        <?php if ( $bena_header_toggle ) :
                            $bena_disable_id    = 'bena-toggle-disable_watermark';
                            $bena_master_checked = $bena_watermark_toggle !== 'disable_watermark';
                            ?>
                            <input type="radio" id="<?php echo esc_attr( $bena_disable_id ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_toggle]" value="disable_watermark" class="bena-watermark-toggle bena-watermark-toggle--disable" style="display:none;" <?php checked( $bena_watermark_toggle, 'disable_watermark' ); ?> />
                            <label class="bena-header-switch" for="bena-watermark-master">
                                <span class="bena-header-switch__text"><?php esc_html_e( 'Enable', 'bena-media-helpers' ); ?></span>
                                <span class="bena-header-switch__control">
                                    <input type="checkbox" id="bena-watermark-master" class="bena-header-switch__input" <?php checked( $bena_master_checked ); ?> />
                                    <span class="bena-header-switch__slider" aria-hidden="true"></span>
                                </span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $bena_group['description'] ) ) : ?>
                        <p class="bena-feature-card__description"><?php echo esc_html( $bena_group['description'] ); ?></p>
                    <?php endif; ?>

                    <?php if ( 'bena-watermark-features' === $bena_group_key ) :
                        $bena_position_value = $this->bena_options['watermark_position'] ?? 'bottom-right';
                        $bena_preview_image  = '';
                        if ( ! empty( $this->bena_options['watermark_attachment'] ) ) {
                            $bena_preview_image = wp_get_attachment_image_url( (int) $this->bena_options['watermark_attachment'], 'thumbnail' ) ?: '';
                        }
                        $bena_preview_text_raw = (string) ( $this->bena_options['watermark_text'] ?? '' );
                        $bena_preview_text     = '' !== $bena_preview_text_raw ? wp_html_excerpt( $bena_preview_text_raw, 20 ) : '';
                        $bena_preview_color    = sanitize_hex_color( $this->bena_options['watermark_text_color'] ?? '#ffffff' ) ?: '#ffffff';
                        $bena_preview_scale    = (int) ( $this->bena_options['watermark_scale'] ?? self::bena_defaults()['watermark_scale'] );
                        $bena_preview_scale    = max( 10, min( 100, $bena_preview_scale ) );
                        ?>
                        <div
                            class="bena-position-grid"
                            role="radiogroup"
                            aria-label="<?php esc_attr_e( 'Watermark position', 'bena-media-helpers' ); ?>"
                            data-preview-image="<?php echo esc_attr( $bena_preview_image ); ?>"
                            data-preview-text="<?php echo esc_attr( $bena_preview_text ); ?>"
                            data-preview-color="<?php echo esc_attr( $bena_preview_color ); ?>"
                            data-preview-scale="<?php echo esc_attr( $bena_preview_scale ); ?>"
                        >
                            <?php foreach ( $this->bena_watermark_positions() as $bena_option_value => $bena_label_text ) :
                                $bena_tile_id = 'bena-position-' . sanitize_html_class( $bena_option_value );
                                $bena_active  = $bena_position_value === $bena_option_value;
                                ?>
                                <label class="bena-position-tile<?php echo $bena_active ? ' bena-position-tile--active' : ''; ?>" for="<?php echo esc_attr( $bena_tile_id ); ?>">
                                    <input type="radio" id="<?php echo esc_attr( $bena_tile_id ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_position]" value="<?php echo esc_attr( $bena_option_value ); ?>" <?php checked( $bena_position_value, $bena_option_value ); ?> />
                                    <span class="bena-position-tile__preview"></span>
                                    <span class="bena-position-tile__label"><?php echo esc_html( $bena_label_text ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="<?php echo esc_attr( $bena_toggles_class ); ?>">
                        <?php foreach ( $bena_items as $bena_key => $bena_item ) :
                            $bena_label        = is_array( $bena_item ) ? ( $bena_item['label'] ?? '' ) : $bena_item;
                            $bena_target       = is_array( $bena_item ) ? ( $bena_item['target'] ?? '' ) : '';
                            $bena_append_field = is_array( $bena_item ) ? ( $bena_item['append_field'] ?? '' ) : '';
                            $bena_append_view  = is_array( $bena_item ) ? ( $bena_item['append_view'] ?? '' ) : '';

                            $bena_value      = (int) ( $this->bena_options[ $bena_key ] ?? 0 );
                            $bena_id         = 'bena-toggle-' . esc_attr( $bena_key );
                            $bena_input_type = ! empty( $bena_group['exclusive'] ) ? 'radio' : 'checkbox';

                            $bena_name        = ! empty( $bena_group['exclusive'] ) ? self::OPTION_KEY . '[watermark_toggle]' : self::OPTION_KEY . '[' . $bena_key . ']';
                            $bena_value_attr  = ! empty( $bena_group['exclusive'] ) ? $bena_key : '1';
                            $bena_has_append  = ! empty( $bena_append_field );
                            $bena_extra_class = '';
                            if ( 'watermark_text' === $bena_append_view ) {
                                $bena_extra_class = ' bena-toggle-item__extra--stack';
                            } elseif ( 'watermark_image' === $bena_append_view ) {
                                $bena_extra_class = ' bena-toggle-item__extra--block';
                            }
                            ?>
                            <div class="bena-toggle-item<?php echo $bena_has_append ? ' bena-toggle-item--with-extra' : ''; ?>">
                                <label class="bena-toggle-item__control" for="<?php echo esc_attr( $bena_id ); ?>">
                                    <input
                                        type="<?php echo esc_attr( $bena_input_type ); ?>"
                                        id="<?php echo esc_attr( $bena_id ); ?>"
                                        name="<?php echo esc_attr( $bena_name ); ?>"
                                        value="<?php echo esc_attr( $bena_value_attr ); ?>"
                                        data-bena-toggle="<?php echo esc_attr( $bena_target ?: $bena_key ); ?>"
                                        <?php echo ! empty( $bena_group['exclusive'] ) ? ' ' . checked( $bena_watermark_toggle, $bena_key, false ) : ' ' . checked( 1, $bena_value, false ); ?>
                                    />
                                    <span class="bena-toggle-item__slider" aria-hidden="true"></span>
                                    <span class="bena-toggle-item__label"><?php echo esc_html( $bena_label ); ?></span>
                                </label>

                                <?php if ( $bena_has_append || $bena_append_view ) : ?>
                                    <div class="bena-toggle-item__extra<?php echo esc_attr( $bena_extra_class ); ?>">
                                        <?php if ( 'webp_quality' === $bena_append_field ) :
                                            $bena_quality = (int) ( $this->bena_options['webp_quality'] ?? 80 );
                                            $bena_quality = max( 10, min( 100, $bena_quality ) );
                                            ?>
                                            <label class="bena-inline-field" for="bena-webp-quality">
                                                <span class="bena-inline-field__label"><?php esc_html_e( 'WebP compression (%)', 'bena-media-helpers' ); ?></span>
                                                <input type="number" id="bena-webp-quality" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[webp_quality]" value="<?php echo esc_attr( $bena_quality ); ?>" min="10" max="100" class="bena-inline-number" />
                                            </label>
                                        <?php elseif ( 'filename_prefix' === $bena_append_field ) :
                                            $bena_prefix = $this->bena_options['filename_prefix'] ?? '';
                                            ?>
                                            <label class="bena-inline-field" for="bena-filename-prefix">
                                                <span class="bena-inline-field__label"><?php esc_html_e( 'Prefix', 'bena-media-helpers' ); ?></span>
                                                <input type="text" id="bena-filename-prefix" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[filename_prefix]" value="<?php echo esc_attr( $bena_prefix ); ?>" class="bena-inline-text" placeholder="<?php echo esc_attr__( 'Example: bena', 'bena-media-helpers' ); ?>" />
                                            </label>
                                        <?php endif; ?>

                                        <?php if ( 'watermark_image' === $bena_append_view ) :
                                            $bena_image_extra = 'enable_watermark' === $bena_watermark_toggle ? '' : 'bena-watermark-disabled';
                                            $this->bena_render_media_field(
                                                [
                                                    'key'           => 'watermark_attachment',
                                                    'description'   => __( 'Select a transparent PNG image to overlay.', 'bena-media-helpers' ),
                                                    'extra_classes' => $bena_image_extra,
                                                ]
                                            );
                                        elseif ( 'watermark_text' === $bena_append_view ) :
                                            $bena_text_value = (string) ( $this->bena_options['watermark_text'] ?? '' );
                                            $bena_text_color = sanitize_hex_color( $this->bena_options['watermark_text_color'] ?? '#ffffff' ) ?: '#ffffff';
                                            $bena_text_size  = (int) ( $this->bena_options['watermark_text_size'] ?? 24 );
                                            $bena_text_size  = max( 8, min( 120, $bena_text_size ) );

                                            $bena_text_classes = 'bena-watermark-text bena-watermark-text-fields';
                                            if ( 'enable_watermark_text' !== $bena_watermark_toggle ) {
                                                $bena_text_classes .= ' bena-watermark-disabled';
                                            }
                                            ?>
                                            <div class="<?php echo esc_attr( $bena_text_classes ); ?>">
                                                <div class="bena-watermark-text-row bena-watermark-text-row--inline">
                                                    <label for="bena-watermark-text" class="bena-watermark-text-row__label"><?php esc_html_e( 'Content', 'bena-media-helpers' ); ?></label>
                                                    <div class="bena-watermark-text-row__control">
                                                        <input type="text" id="bena-watermark-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_text]" value="<?php echo esc_attr( $bena_text_value ); ?>" class="bena-watermark-text-row__input" placeholder="<?php echo esc_attr__( 'Example: © bena.vn', 'bena-media-helpers' ); ?>" />
                                                    </div>
                                                </div>

                                                <div class="bena-watermark-text-row bena-watermark-text-row--inline">
                                                    <label for="bena-watermark-text-color" class="bena-watermark-text-row__label"><?php esc_html_e( 'Text color', 'bena-media-helpers' ); ?></label>
                                                    <div class="bena-watermark-text-row__control">
                                                        <input type="color" id="bena-watermark-text-color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_text_color]" value="<?php echo esc_attr( $bena_text_color ); ?>" class="bena-watermark-text-row__color" />
                                                    </div>
                                                </div>

                                                <div class="bena-watermark-text-row bena-watermark-text-row--inline">
                                                    <label for="bena-watermark-text-size" class="bena-watermark-text-row__label"><?php esc_html_e( 'Size (pt)', 'bena-media-helpers' ); ?></label>
                                                    <div class="bena-watermark-text-row__control">
                                                        <select id="bena-watermark-text-size" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_text_size]" class="bena-watermark-text-row__select">
                                                            <?php foreach ( [ 12, 14, 16, 18, 20, 24, 28, 32, 40, 48, 60, 72, 90, 120 ] as $bena_size_option ) : ?>
                                                                <option value="<?php echo esc_attr( $bena_size_option ); ?>" <?php selected( $bena_text_size, $bena_size_option ); ?>><?php echo esc_html( $bena_size_option ); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( 'bena-watermark-features' === $bena_group_key ) :
                        $bena_scale   = (int) ( $this->bena_options['watermark_scale'] ?? self::bena_defaults()['watermark_scale'] );
                        $bena_opacity = (int) ( $this->bena_options['watermark_opacity'] ?? self::bena_defaults()['watermark_opacity'] );

                        $bena_scale   = max( 10, min( 100, $bena_scale ) );
                        $bena_opacity = max( 0, min( 100, $bena_opacity ) );

                        $bena_shared_classes = 'bena-watermark-shared';
                        if ( 'disable_watermark' === $bena_watermark_toggle ) {
                            $bena_shared_classes .= ' bena-hidden';
                        }
                        ?>
                        <div class="<?php echo esc_attr( $bena_shared_classes ); ?>">
                            <div class="bena-watermark-shared__title"><?php esc_html_e( 'Customize watermark', 'bena-media-helpers' ); ?></div>

                            <div class="bena-watermark-row">
                                <div class="bena-watermark-row__label"><?php esc_html_e( 'Watermark scale (%)', 'bena-media-helpers' ); ?></div>
                                <div class="bena-watermark-row__control">
                                    <input type="range" id="bena-watermark-scale" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_scale]" value="<?php echo esc_attr( $bena_scale ); ?>" min="10" max="100" class="bena-watermark-row__range" />
                                    <span class="bena-watermark-row__value" data-bena-bind="watermark_scale"><?php echo esc_html( $bena_scale ); ?>%</span>
                                </div>
                            </div>

                            <div class="bena-watermark-row">
                                <div class="bena-watermark-row__label"><?php esc_html_e( 'Opacity (%)', 'bena-media-helpers' ); ?></div>
                                <div class="bena-watermark-row__control">
                                    <input type="range" id="bena-watermark-opacity" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[watermark_opacity]" value="<?php echo esc_attr( $bena_opacity ); ?>" min="0" max="100" class="bena-watermark-row__range" />
                                    <span class="bena-watermark-row__value" data-bena-bind="watermark_opacity"><?php echo esc_html( $bena_opacity ); ?>%</span>
                                </div>
                            </div>
                        </div>

                        <div class="bena-form-actions">
                            <button type="submit" name="submit" class="bena-submit"><?php esc_html_e( 'Save settings', 'bena-media-helpers' ); ?></button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function bena_defaults(): array {
        return [
            'enable_remote_images'       => 1,
            'enable_delete_attachments'  => 1,
            'enable_convert_webp'        => 1,
            'enable_watermark'           => 0,
            'enable_watermark_text'      => 0,
            'enable_filename_prefix'     => 0,
            'webp_quality'               => 80,
            'watermark_attachment'       => 0,
            'watermark_text'             => '',
            'watermark_scale'            => 80,
            'watermark_opacity'          => 80,
            'watermark_text_color'       => '#ffffff',
            'watermark_text_size'        => 24,
            'watermark_position'         => 'bottom-right',
            'filename_prefix'            => '',
        ];
    }

    public function bena_handle_remote_images( $bena_content ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $bena_content;
        }

        if ( ! isset( $_POST['save'] ) && ! isset( $_POST['publish'] ) ) {
            return $bena_content;
        }

        global $post;
        $bena_post_id = isset( $post->ID ) ? (int) $post->ID : 0;

        if ( ! $bena_post_id ) {
            return $bena_content;
        }

        $bena_matches = [];
        if ( ! preg_match_all( '/<img[^>]+src="(.*?)"/i', stripslashes( $bena_content ), $bena_matches ) ) {
            return $bena_content;
        }

        remove_filter( 'content_save_pre', [ $this, 'bena_handle_remote_images' ] );

        foreach ( $bena_matches[1] as $bena_image_url ) {
            if ( empty( $bena_image_url ) || strpos( $bena_image_url, $_SERVER['HTTP_HOST'] ) !== false ) {
                continue;
            }

            $bena_image_data = @file_get_contents( $bena_image_url );
            if ( false === $bena_image_data ) {
                continue;
            }

            $bena_post_object = get_post( $bena_post_id );
            $bena_post_name   = $bena_post_object ? sanitize_title( $bena_post_object->post_title ) : 'image';
            $bena_filename    = sprintf( '%s-%d.jpg', $bena_post_name, $bena_post_id );
            $bena_filename    = $this->bena_apply_prefix_to_filename( $bena_filename );

            $bena_upload = wp_upload_bits( $bena_filename, '', $bena_image_data );
            if ( $bena_upload['error'] ) {
                continue;
            }

            $this->bena_process_local_image( $bena_upload['file'] );

            $bena_attachment_id = $this->bena_insert_attachment( $bena_upload['file'], $bena_post_id );
            if ( ! $bena_attachment_id ) {
                continue;
            }

            $bena_new_url = wp_get_attachment_url( $bena_attachment_id );
            if ( $bena_new_url ) {
                $bena_content = str_replace( $bena_image_url, $bena_new_url, $bena_content );
            }
        }

        add_filter( 'content_save_pre', [ $this, 'bena_handle_remote_images' ] );

        return $bena_content;
    }

    private function bena_insert_attachment( $bena_file_path, $bena_post_id ) {
        $bena_filetype = wp_check_filetype( $bena_file_path );
        $bena_dirs     = wp_upload_dir();

        $bena_attachment_title = preg_replace( '/\.[^.]+$/', '', basename( $bena_file_path ) );
        if ( ! empty( $this->bena_options['enable_filename_prefix'] ) && '' !== trim( (string) $this->bena_options['filename_prefix'] ) ) {
            $bena_attachment_title = $this->bena_options['filename_prefix'] . $bena_attachment_title;
        }

        $bena_attachment = [
            'guid'           => $bena_dirs['baseurl'] . '/' . _wp_relative_upload_path( $bena_file_path ),
            'post_mime_type' => $bena_filetype['type'],
            'post_title'     => $bena_attachment_title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $bena_attach_id = wp_insert_attachment( $bena_attachment, $bena_file_path, $bena_post_id );
        if ( is_wp_error( $bena_attach_id ) || ! $bena_attach_id ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $bena_metadata = wp_generate_attachment_metadata( $bena_attach_id, $bena_file_path );
        wp_update_attachment_metadata( $bena_attach_id, $bena_metadata );

        return (int) $bena_attach_id;
    }

    public function bena_delete_post_attachments( $bena_post_id ) {
        if ( get_post_type( $bena_post_id ) !== 'post' ) {
            return;
        }

        $bena_attachments = get_attached_media( '', $bena_post_id );
        if ( empty( $bena_attachments ) ) {
            return;
        }

        foreach ( $bena_attachments as $bena_attachment ) {
            wp_delete_attachment( (int) $bena_attachment->ID, true );
        }
    }

    public function bena_process_upload( $bena_upload ) {
        if ( empty( $bena_upload['file'] ) || ! file_exists( $bena_upload['file'] ) ) {
            return $bena_upload;
        }

        $bena_upload_dir  = wp_upload_dir();
        $bena_current_dir = dirname( $bena_upload['file'] );
        $bena_basename    = wp_basename( $bena_upload['file'] );

        // Optional prefix renaming.
        $bena_prefixed_name = $this->bena_apply_prefix_to_filename( $bena_basename );
        if ( $bena_prefixed_name !== $bena_basename ) {
            $bena_unique     = wp_unique_filename( $bena_current_dir, $bena_prefixed_name );
            $bena_new_path   = trailingslashit( $bena_current_dir ) . $bena_unique;
            $bena_relative   = str_replace( $bena_upload_dir['basedir'], '', $bena_current_dir );
            $bena_relative   = ltrim( str_replace( '\\', '/', $bena_relative ), '/' );
            $bena_new_url    = trailingslashit( $bena_upload_dir['baseurl'] . ( $bena_relative ? '/' . $bena_relative : '' ) ) . $bena_unique;

            if ( @rename( $bena_upload['file'], $bena_new_path ) ) {
                $bena_upload['file'] = $bena_new_path;
                $bena_upload['url']  = $bena_new_url;
                $bena_basename       = $bena_unique;
            }
        }

        $bena_info = @getimagesize( $bena_upload['file'] );
        $this->bena_process_watermarks( $bena_upload['file'], $bena_info );

        return $bena_upload;
    }

    private function bena_process_local_image( string $bena_file ): void {
        if ( ! file_exists( $bena_file ) ) {
            return;
        }

        $bena_info = @getimagesize( $bena_file );
        $this->bena_process_watermarks( $bena_file, $bena_info );
    }

    private function bena_process_watermarks( string $bena_file_path, ?array $bena_info ): void {
        if ( ! $bena_info ) {
            return;
        }

        $bena_mime = $bena_info['mime'] ?? '';

        if ( $this->bena_options['enable_watermark'] ) {
            $this->bena_maybe_apply_watermark( $bena_file_path, $bena_mime );
        }

        if ( $this->bena_options['enable_watermark_text'] ) {
            $this->bena_maybe_apply_text_watermark( $bena_file_path, $bena_info );
        }
    }

    private function bena_maybe_apply_watermark( string $bena_file_path, string $bena_mime ): void {
        if ( empty( $this->bena_options['enable_watermark'] ) ) {
            return;
        }

        $bena_attachment_id = (int) $this->bena_options['watermark_attachment'];
        if ( ! $bena_attachment_id ) {
            return;
        }

        $bena_watermark_path = get_attached_file( $bena_attachment_id );
        if ( ! $bena_watermark_path || ! file_exists( $bena_watermark_path ) ) {
            return;
        }

        $bena_supported = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
        ];

        if ( ! isset( $bena_supported[ $bena_mime ] ) ) {
            return;
        }

        $bena_create_image = $bena_supported[ $bena_mime ];
        $bena_image = @$bena_create_image( $bena_file_path );
        if ( ! $bena_image ) {
            return;
        }

        $bena_watermark = @imagecreatefrompng( $bena_watermark_path );
        if ( ! $bena_watermark ) {
            imagedestroy( $bena_image );
            return;
        }

        imagealphablending( $bena_watermark, true );
        imagesavealpha( $bena_watermark, true );

        $bena_img_width  = imagesx( $bena_image );
        $bena_img_height = imagesy( $bena_image );
        $bena_wm_width   = imagesx( $bena_watermark );
        $bena_wm_height  = imagesy( $bena_watermark );
        $bena_scale_percent = max( 10, min( 100, (int) ( $this->bena_options['watermark_scale'] ?? 80 ) ) );
        $bena_scale_factor  = $bena_scale_percent / 100;

        $bena_target_width  = max( 1, (int) round( $bena_img_width * $bena_scale_factor ) );
        $bena_target_height = max( 1, (int) round( $bena_img_height * $bena_scale_factor ) );

        if ( $bena_wm_width > $bena_target_width || $bena_wm_height > $bena_target_height ) {
            $bena_scale = min( $bena_target_width / $bena_wm_width, $bena_target_height / $bena_wm_height );
            $bena_scale = max( 0.1, min( 1, $bena_scale ) );
            $bena_new_w = max( 1, (int) round( $bena_wm_width * $bena_scale ) );
            $bena_new_h = max( 1, (int) round( $bena_wm_height * $bena_scale ) );
            $bena_resized = imagecreatetruecolor( $bena_new_w, $bena_new_h );
            imagealphablending( $bena_resized, false );
            imagesavealpha( $bena_resized, true );
            imagecopyresampled( $bena_resized, $bena_watermark, 0, 0, 0, 0, $bena_new_w, $bena_new_h, $bena_wm_width, $bena_wm_height );
            imagedestroy( $bena_watermark );
            $bena_watermark = $bena_resized;
            $bena_wm_width  = $bena_new_w;
            $bena_wm_height = $bena_new_h;
        }

        $bena_opacity_percent = max( 0, min( 100, (int) ( $this->bena_options['watermark_opacity'] ?? 80 ) ) );
        $bena_alpha = (int) round( ( 100 - $bena_opacity_percent ) * 1.27 );
        $bena_alpha = max( 0, min( 127, $bena_alpha ) );
        $this->bena_apply_png_opacity( $bena_watermark, $bena_alpha );

        imagealphablending( $bena_image, true );
        if ( 'image/png' === $bena_mime ) {
            imagesavealpha( $bena_image, true );
        }

        list( $bena_dst_x, $bena_dst_y ) = $this->bena_calculate_position( $bena_img_width, $bena_img_height, $bena_wm_width, $bena_wm_height, 20 );

        imagecopy( $bena_image, $bena_watermark, $bena_dst_x, $bena_dst_y, 0, 0, $bena_wm_width, $bena_wm_height );

        imagedestroy( $bena_watermark );

        if ( 'image/jpeg' === $bena_mime ) {
            imagejpeg( $bena_image, $bena_file_path, 90 );
        } else {
            imagepng( $bena_image, $bena_file_path, 6 );
        }

        imagedestroy( $bena_image );
    }

    private function bena_apply_png_opacity( $bena_image, int $bena_alpha ): void {
        $bena_is_gd = is_resource( $bena_image );
        if ( ! $bena_is_gd && class_exists( 'GdImage', false ) ) {
            $bena_is_gd = $bena_image instanceof \GdImage;
        }

        if ( ! $bena_is_gd ) {
            return;
        }

        $bena_alpha = max( 0, min( 127, $bena_alpha ) );
        $bena_width  = imagesx( $bena_image );
        $bena_height = imagesy( $bena_image );

        imagealphablending( $bena_image, false );
        for ( $bena_x = 0; $bena_x < $bena_width; $bena_x++ ) {
            for ( $bena_y = 0; $bena_y < $bena_height; $bena_y++ ) {
                $bena_color_index = imagecolorat( $bena_image, $bena_x, $bena_y );
                $bena_alpha_pixel = ( $bena_color_index & 0x7F000000 ) >> 24;
                $bena_new_alpha   = max( 0, min( 127, $bena_alpha_pixel + $bena_alpha ) );
                $bena_red         = ( $bena_color_index >> 16 ) & 0xFF;
                $bena_green       = ( $bena_color_index >> 8 ) & 0xFF;
                $bena_blue        = $bena_color_index & 0xFF;
                $bena_new_color   = imagecolorallocatealpha( $bena_image, $bena_red, $bena_green, $bena_blue, $bena_new_alpha );
                imagesetpixel( $bena_image, $bena_x, $bena_y, $bena_new_color );
            }
        }
        imagesavealpha( $bena_image, true );
    }

    private function bena_maybe_apply_text_watermark( string $bena_file_path, array $bena_info ): void {
        if ( empty( $this->bena_options['enable_watermark_text'] ) ) {
            return;
        }

        $bena_text = trim( (string) ( $this->bena_options['watermark_text'] ?? '' ) );
        if ( '' === $bena_text ) {
            return;
        }

        $bena_mime = $bena_info['mime'] ?? '';
        $bena_supported = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png'  => 'imagecreatefrompng',
        ];

        if ( ! isset( $bena_supported[ $bena_mime ] ) ) {
            return;
        }

        $bena_create = $bena_supported[ $bena_mime ];
        $bena_image  = @$bena_create( $bena_file_path );
        if ( ! $bena_image ) {
            return;
        }

        if ( 'image/png' === $bena_mime ) {
            imagealphablending( $bena_image, true );
            imagesavealpha( $bena_image, true );
        } else {
            imagealphablending( $bena_image, true );
        }

        $bena_width  = imagesx( $bena_image );
        $bena_height = imagesy( $bena_image );

        $bena_rgb   = $this->bena_hex_to_rgb( $this->bena_options['watermark_text_color'] ?? '#ffffff' );
        $bena_opacity_percent = max( 0, min( 100, (int) ( $this->bena_options['watermark_opacity'] ?? 80 ) ) );
        $bena_alpha = (int) round( ( 100 - $bena_opacity_percent ) * 1.27 );
        $bena_alpha = max( 0, min( 127, $bena_alpha ) );
        $bena_color = imagecolorallocatealpha( $bena_image, $bena_rgb[0], $bena_rgb[1], $bena_rgb[2], $bena_alpha );

        $bena_font_size = max( 8, min( 120, (int) ( $this->bena_options['watermark_text_size'] ?? 24 ) ) );
        $bena_margin    = 24;

        $bena_font_path = $this->bena_locate_watermark_font();

        if ( function_exists( 'imagettfbbox' ) && $bena_font_path ) {
            $bena_box    = @imagettfbbox( $bena_font_size, 0, $bena_font_path, $bena_text );
            $bena_text_w = $bena_box ? (int) abs( $bena_box[4] - $bena_box[0] ) : 0;
            $bena_text_h = $bena_box ? (int) abs( $bena_box[5] - $bena_box[1] ) : 0;

            if ( $bena_box && $bena_text_w > 0 && $bena_text_h > 0 ) {
                list( $bena_x, $bena_y ) = $this->bena_calculate_position( $bena_width, $bena_height, $bena_text_w, $bena_text_h, $bena_margin );
                $bena_y += $bena_text_h;
                imagettftext( $bena_image, $bena_font_size, 0, $bena_x, $bena_y, $bena_color, $bena_font_path, $bena_text );
            } else {
                $this->bena_draw_text_fallback( $bena_image, $bena_width, $bena_height, $bena_text, $bena_color, $bena_margin );
            }
        } else {
            $this->bena_draw_text_fallback( $bena_image, $bena_width, $bena_height, $bena_text, $bena_color, $bena_margin );
        }

        if ( 'image/jpeg' === $bena_mime ) {
            imagejpeg( $bena_image, $bena_file_path, 90 );
        } else {
            imagepng( $bena_image, $bena_file_path, 6 );
        }

        imagedestroy( $bena_image );
    }

    private function bena_draw_text_fallback( $bena_image, int $bena_width, int $bena_height, string $bena_text, int $bena_color, int $bena_margin ): void {
        $bena_font   = 5;
        $bena_text_w = imagefontwidth( $bena_font ) * strlen( $bena_text );
        $bena_text_h = imagefontheight( $bena_font );

        list( $bena_x, $bena_y ) = $this->bena_calculate_position( $bena_width, $bena_height, $bena_text_w, $bena_text_h, $bena_margin );
        imagestring( $bena_image, $bena_font, $bena_x, $bena_y, $bena_text, $bena_color );
    }

    private function bena_apply_prefix_to_filename( string $bena_filename ): string {
        if ( empty( $this->bena_options['enable_filename_prefix'] ) ) {
            return $bena_filename;
        }

        $bena_prefix_raw = (string) ( $this->bena_options['filename_prefix'] ?? '' );
        $bena_prefix     = sanitize_title_with_dashes( $bena_prefix_raw );
        if ( '' === $bena_prefix ) {
            return $bena_filename;
        }

        $bena_parts = pathinfo( $bena_filename );
        $bena_name  = $bena_parts['filename'] ?? $bena_filename;
        $bena_ext   = isset( $bena_parts['extension'] ) ? '.' . $bena_parts['extension'] : '';

        $bena_lower_name = strtolower( $bena_name );
        if ( 0 === strpos( $bena_lower_name, $bena_prefix . '-' ) || 0 === strpos( $bena_lower_name, $bena_prefix . '_' ) ) {
            return $bena_name . $bena_ext;
        }

        return $bena_prefix . '-' . ltrim( $bena_name, '-_' ) . $bena_ext;
    }

    private function bena_calculate_position( int $bena_img_w, int $bena_img_h, int $bena_wm_w, int $bena_wm_h, int $bena_margin ): array {
        $bena_position = $this->bena_options['watermark_position'] ?? 'bottom-right';

        $bena_x = $bena_margin;
        $bena_y = $bena_margin;

        switch ( $bena_position ) {
            case 'top-center':
                $bena_x = ( $bena_img_w - $bena_wm_w ) / 2;
                $bena_y = $bena_margin;
                break;
            case 'top-right':
                $bena_x = $bena_img_w - $bena_wm_w - $bena_margin;
                $bena_y = $bena_margin;
                break;
            case 'center-left':
                $bena_x = $bena_margin;
                $bena_y = ( $bena_img_h - $bena_wm_h ) / 2;
                break;
            case 'center':
                $bena_x = ( $bena_img_w - $bena_wm_w ) / 2;
                $bena_y = ( $bena_img_h - $bena_wm_h ) / 2;
                break;
            case 'center-right':
                $bena_x = $bena_img_w - $bena_wm_w - $bena_margin;
                $bena_y = ( $bena_img_h - $bena_wm_h ) / 2;
                break;
            case 'bottom-left':
                $bena_x = $bena_margin;
                $bena_y = $bena_img_h - $bena_wm_h - $bena_margin;
                break;
            case 'bottom-center':
                $bena_x = ( $bena_img_w - $bena_wm_w ) / 2;
                $bena_y = $bena_img_h - $bena_wm_h - $bena_margin;
                break;
            case 'top-left':
                $bena_x = $bena_margin;
                $bena_y = $bena_margin;
                break;
            case 'bottom-right':
            default:
                $bena_x = $bena_img_w - $bena_wm_w - $bena_margin;
                $bena_y = $bena_img_h - $bena_wm_h - $bena_margin;
                break;
        }

        return [ (int) round( max( 0, $bena_x ) ), (int) round( max( 0, $bena_y ) ) ];
    }

    private function bena_hex_to_rgb( string $bena_hex ): array {
        $bena_hex = ltrim( $bena_hex, '#' );
        if ( 3 === strlen( $bena_hex ) ) {
            $bena_hex = $bena_hex[0] . $bena_hex[0] . $bena_hex[1] . $bena_hex[1] . $bena_hex[2] . $bena_hex[2];
        }

        $bena_int = hexdec( substr( $bena_hex, 0, 6 ) );

        return [ ( $bena_int >> 16 ) & 255, ( $bena_int >> 8 ) & 255, $bena_int & 255 ];
    }

    private function bena_locate_watermark_font(): ?string {
        static $bena_cached_font = null;

        if ( null !== $bena_cached_font ) {
            return '' !== $bena_cached_font ? $bena_cached_font : null;
        }

        $bena_candidates = apply_filters(
            'bena_media_helpers_watermark_fonts',
            [
                plugin_dir_path( __FILE__ ) . 'fonts/bena-watermark.ttf',
                plugin_dir_path( __FILE__ ) . 'fonts/Inter-Regular.ttf',
                plugin_dir_path( __FILE__ ) . 'fonts/Roboto-Regular.ttf',
                ABSPATH . 'wp-content/fonts/bena-watermark.ttf',
                ABSPATH . 'wp-content/uploads/bena-watermark.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
                '/Library/Fonts/Arial.ttf',
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\segoeui.ttf',
                'C:\\Windows\\Fonts\\tahoma.ttf',
            ]
        );

        foreach ( $bena_candidates as $bena_path ) {
            if ( ! $bena_path ) {
                continue;
            }

            $bena_normalized = wp_normalize_path( $bena_path );
            if ( file_exists( $bena_normalized ) && is_readable( $bena_normalized ) ) {
                $bena_cached_font = $bena_normalized;

                return $bena_cached_font;
            }
        }

        $bena_cached_font = '';

        return null;
    }

    public function bena_convert_upload_to_webp( $bena_upload ) {
        if ( empty( $bena_upload['file'] ) || ! file_exists( $bena_upload['file'] ) ) {
            return $bena_upload;
        }

        if ( ! function_exists( 'imagewebp' ) || ! function_exists( 'imagecreatefromstring' ) ) {
            return $bena_upload;
        }

        $bena_info = @getimagesize( $bena_upload['file'] );
        if ( ! $bena_info || ! in_array( $bena_info['mime'], [ 'image/jpeg', 'image/png' ], true ) ) {
            return $bena_upload;
        }

        $bena_raw = @file_get_contents( $bena_upload['file'] );
        if ( false === $bena_raw ) {
            return $bena_upload;
        }

        $bena_image = @imagecreatefromstring( $bena_raw );
        if ( ! $bena_image || ( function_exists( 'imageistruecolor' ) && ! imageistruecolor( $bena_image ) ) ) {
            if ( $bena_image ) {
                imagedestroy( $bena_image );
            }
            return $bena_upload;
        }

        $bena_dir       = wp_upload_dir();
        $bena_basename  = wp_basename( $bena_upload['file'] );
        $bena_basename  = $this->bena_apply_prefix_to_filename( $bena_basename );
        $bena_webp_name = wp_unique_filename( $bena_dir['path'], preg_replace( '/\.(jpe?g|png)$/i', '.webp', $bena_basename ) );
        $bena_webp_path = trailingslashit( $bena_dir['path'] ) . $bena_webp_name;

        $bena_quality = apply_filters( 'bena_webp_quality', (int) $this->bena_options['webp_quality'], $bena_upload );
        $bena_quality = max( 10, min( 100, (int) $bena_quality ) );

        $bena_converted = imagewebp( $bena_image, $bena_webp_path, $bena_quality );
        imagedestroy( $bena_image );

        if ( ! $bena_converted || ! file_exists( $bena_webp_path ) ) {
            return $bena_upload;
        }

        @unlink( $bena_upload['file'] );

        $bena_upload['file'] = $bena_webp_path;
        $bena_upload['url']  = trailingslashit( $bena_dir['url'] ) . $bena_webp_name;
        $bena_upload['type'] = 'image/webp';

        return $bena_upload;
    }

    public function bena_register_menu(): void {
        add_options_page(
            __( 'Bena Media Helpers', 'bena-media-helpers' ),
            __( 'Bena Media Helpers', 'bena-media-helpers' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'bena_render_settings_page' ]
        );
    }

    public function bena_register_settings(): void {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, [ $this, 'bena_sanitize_options' ] );

        add_settings_section(
            'bena_media_helpers_main',
            '',
            '__return_false',
            self::OPTION_KEY
        );

    }

    public function bena_sanitize_options( array $bena_input ): array {
        $bena_defaults = self::bena_defaults();
        $bena_output   = $bena_defaults;

        $bena_watermark_toggle = $bena_input['watermark_toggle'] ?? '';
        $bena_output['enable_watermark']      = $bena_watermark_toggle === 'enable_watermark' ? 1 : 0;
        $bena_output['enable_watermark_text'] = $bena_watermark_toggle === 'enable_watermark_text' ? 1 : 0;

        foreach ( $bena_defaults as $bena_key => $bena_default ) {
            if ( in_array( $bena_key, [ 'enable_remote_images', 'enable_delete_attachments', 'enable_convert_webp', 'enable_filename_prefix' ], true ) ) {
                $bena_output[ $bena_key ] = isset( $bena_input[ $bena_key ] ) ? 1 : 0;
            } elseif ( 'webp_quality' === $bena_key ) {
                $bena_value = isset( $bena_input[ $bena_key ] ) ? (int) $bena_input[ $bena_key ] : $bena_default;
                $bena_output[ $bena_key ] = max( 10, min( 100, $bena_value ) );
            } elseif ( 'watermark_attachment' === $bena_key ) {
                $bena_attachment_id = isset( $bena_input[ $bena_key ] ) ? (int) $bena_input[ $bena_key ] : 0;
                $bena_output[ $bena_key ] = $bena_attachment_id > 0 ? $bena_attachment_id : 0;
            } elseif ( 'watermark_scale' === $bena_key ) {
                $bena_scale = isset( $bena_input[ $bena_key ] ) ? (int) $bena_input[ $bena_key ] : $bena_default;
                $bena_output[ $bena_key ] = max( 10, min( 100, $bena_scale ) );
            } elseif ( 'watermark_opacity' === $bena_key ) {
                $bena_opacity = isset( $bena_input[ $bena_key ] ) ? (int) $bena_input[ $bena_key ] : $bena_default;
                $bena_output[ $bena_key ] = max( 0, min( 100, $bena_opacity ) );
            } elseif ( in_array( $bena_key, [ 'watermark_text', 'filename_prefix' ], true ) ) {
                $bena_output[ $bena_key ] = sanitize_text_field( $bena_input[ $bena_key ] ?? '' );
            } elseif ( 'watermark_text_color' === $bena_key ) {
                $bena_color = sanitize_hex_color( $bena_input[ $bena_key ] ?? '#ffffff' );
                $bena_output[ $bena_key ] = $bena_color ? $bena_color : '#ffffff';
            } elseif ( 'watermark_text_size' === $bena_key ) {
                $bena_size = isset( $bena_input[ $bena_key ] ) ? (int) $bena_input[ $bena_key ] : $bena_default;
                $bena_output[ $bena_key ] = max( 8, min( 120, $bena_size ) );
            } elseif ( 'watermark_position' === $bena_key ) {
                $bena_positions = array_keys( $this->bena_watermark_positions() );
                $bena_choice    = $bena_input[ $bena_key ] ?? $bena_default;
                $bena_output[ $bena_key ] = in_array( $bena_choice, $bena_positions, true ) ? $bena_choice : 'bottom-right';
            }
        }

        return $bena_output;
    }

    public function bena_render_toggle_field( array $bena_args ): void {
        $bena_key   = $bena_args['key'];
        $bena_value = (int) $this->bena_options[ $bena_key ];
        $bena_id    = 'bena-media-' . esc_attr( $bena_key );

        echo '<div class="bena-toggle-wrapper">';
        echo '<label class="bena-switch" for="' . esc_attr( $bena_id ) . '">';
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s />',
            esc_attr( $bena_id ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $bena_key ),
            checked( 1, $bena_value, false )
        );
        echo '<span class="bena-switch__slider" aria-hidden="true"></span>';
        echo '</label>';

        if ( ! empty( $bena_args['description'] ) ) {
            echo '<p class="description">' . esc_html( $bena_args['description'] ) . '</p>';
        }

        echo '</div>';
    }

    public function bena_render_number_field( array $bena_args ): void {
        $bena_key   = $bena_args['key'];
        $bena_value = (int) ( $this->bena_options[ $bena_key ] ?? 0 );
        $bena_description = $bena_args['description'] ?? '';
        $bena_min = isset( $bena_args['min'] ) ? (int) $bena_args['min'] : 10;
        $bena_max = isset( $bena_args['max'] ) ? (int) $bena_args['max'] : 100;

        $bena_wrapper_classes = 'bena-input-number-wrapper';
        if ( 'watermark_text_size' === $bena_key ) {
            $bena_wrapper_classes .= ' bena-watermark-text';
        }

        echo '<div class="' . esc_attr( $bena_wrapper_classes ) . '">';
        printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$d" min="%4$d" max="%5$d" class="bena-input-number" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $bena_key ),
            $bena_value,
            $bena_min,
            $bena_max
        );

        if ( $bena_description ) {
            echo '<p class="description">' . esc_html( $bena_description ) . '</p>';
        }
        echo '</div>';
    }

    public function bena_render_text_field( array $bena_args ): void {
        $bena_key         = $bena_args['key'];
        $bena_value       = $this->bena_options[ $bena_key ] ?? '';
        $bena_placeholder = $bena_args['placeholder'] ?? '';
        $bena_description = $bena_args['description'] ?? '';

        $bena_wrapper_classes = 'bena-input-text-wrapper';
        if ( 'watermark_text' === $bena_key ) {
            $bena_wrapper_classes .= ' bena-watermark-text';
        }

        echo '<div class="' . esc_attr( $bena_wrapper_classes ) . '">';
        printf(
            '<input type="text" name="%1$s[%2$s]" value="%3$s" class="bena-input-text" placeholder="%4$s" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $bena_key ),
            esc_attr( $bena_value ),
            esc_attr( $bena_placeholder )
        );

        if ( $bena_description ) {
            echo '<p class="description">' . esc_html( $bena_description ) . '</p>';
        }
        echo '</div>';
    }

    public function bena_render_color_field( array $bena_args ): void {
        $bena_key   = $bena_args['key'];
        $bena_value = $this->bena_options[ $bena_key ] ?? '#ffffff';

        echo '<div class="bena-color-wrapper bena-watermark-text">';
        printf(
            '<input type="color" name="%1$s[%2$s]" value="%3$s" class="bena-input-color" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $bena_key ),
            esc_attr( $bena_value )
        );
        echo '</div>';
    }

    public function bena_render_select_field( array $bena_args ): void {
        $bena_key     = $bena_args['key'];
        $bena_options = $bena_args['options'] ?? [];
        $bena_value   = $this->bena_options[ $bena_key ] ?? '';

        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $bena_key ) . ']" class="bena-select">';
        foreach ( $bena_options as $bena_option_value => $bena_label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $bena_option_value ),
                selected( $bena_value, $bena_option_value, false ),
                esc_html( $bena_label )
            );
        }
        echo '</select>';
    }

    private function bena_watermark_positions(): array {
        return [
            'top-left'      => __( 'Top - Left', 'bena-media-helpers' ),
            'top-center'    => __( 'Top - Center', 'bena-media-helpers' ),
            'top-right'     => __( 'Top - Right', 'bena-media-helpers' ),
            'center-left'   => __( 'Center - Left', 'bena-media-helpers' ),
            'center'        => __( 'Center', 'bena-media-helpers' ),
            'center-right'  => __( 'Center - Right', 'bena-media-helpers' ),
            'bottom-left'   => __( 'Bottom - Left', 'bena-media-helpers' ),
            'bottom-center' => __( 'Bottom - Center', 'bena-media-helpers' ),
            'bottom-right'  => __( 'Bottom - Right', 'bena-media-helpers' ),
        ];
    }

    public function bena_render_media_field( array $bena_args ): void {
        $bena_key         = $bena_args['key'];
        $bena_value       = (int) $this->bena_options[ $bena_key ];
        $bena_id          = 'bena-media-' . esc_attr( $bena_key );
        $bena_image_url   = $bena_value ? wp_get_attachment_image_url( $bena_value, 'medium' ) : '';
        $bena_description = $bena_args['description'] ?? '';
        $bena_extra_classes_raw = isset( $bena_args['extra_classes'] ) ? (string) $bena_args['extra_classes'] : '';
        $bena_extra_classes      = '';

        if ( $bena_extra_classes_raw ) {
            $bena_extra_array = preg_split( '/\s+/', $bena_extra_classes_raw );
            $bena_extra_array = array_filter( array_map( 'sanitize_html_class', $bena_extra_array ) );
            if ( ! empty( $bena_extra_array ) ) {
                $bena_extra_classes = ' ' . implode( ' ', $bena_extra_array );
            }
        }

        echo '<div class="bena-media-upload bena-watermark-image' . $bena_extra_classes . '" data-target="' . esc_attr( $bena_id ) . '">';
        printf(
            '<input type="hidden" id="%1$s" name="%2$s[%3$s]" value="%4$d" />',
            esc_attr( $bena_id ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $bena_key ),
            $bena_value
        );

        echo '<div class="bena-media-upload__preview">';
        if ( $bena_image_url ) {
            echo '<img src="' . esc_url( $bena_image_url ) . '" alt="" />';
        } else {
            echo '<span class="bena-media-upload__placeholder">' . esc_html__( 'No watermark selected', 'bena-media-helpers' ) . '</span>';
        }
        echo '</div>';

        echo '<div class="bena-media-upload__actions">';
        echo '<button type="button" class="button bena-media-upload__choose">' . esc_html__( 'Choose watermark', 'bena-media-helpers' ) . '</button>';
        $bena_remove_style = $bena_image_url ? '' : ' style="display:none"';
        echo '<button type="button" class="button-link-delete bena-media-upload__remove"' . $bena_remove_style . '>' . esc_html__( 'Remove watermark', 'bena-media-helpers' ) . '</button>';
        echo '</div>';

        if ( $bena_description ) {
            echo '<p class="description">' . esc_html( $bena_description ) . '</p>';
        }

        echo '</div>';
    }

    private function bena_render_sidebar(): void {
        ?>
        <aside class="bena-media-sidebar">
            <section class="bena-sidebar-card bena-sidebar-card--products">
                <header class="bena-sidebar-card__header">
                    <span class="bena-sidebar-card__icon dashicons dashicons-store"></span>
                    <div>
                        <h3 class="bena-sidebar-card__title"><?php esc_html_e( 'Other products', 'bena-media-helpers' ); ?></h3>
                        <p class="bena-sidebar-card__subtitle"><?php esc_html_e( 'Our other products and services', 'bena-media-helpers' ); ?></p>
                    </div>
                </header>
                <ul class="bena-sidebar-list">
                    <li><a href="<?php echo esc_url( 'https://rutgon.com.vn/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Link shortener and cloaking', 'bena-media-helpers' ); ?></a></li>
                    <li><a href="<?php echo esc_url( 'https://bena.asia' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Website design', 'bena-media-helpers' ); ?></a></li>
                    <li><a href="<?php echo esc_url( 'https://fbena.com' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Social media services', 'bena-media-helpers' ); ?></a></li>
                    <li><a href="<?php echo esc_url( 'https://rutgon.com.vn/user/integrations/popup_affiliate' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Popup Affiliate plugin', 'bena-media-helpers' ); ?></a></li>
                    <li><a href="<?php echo esc_url( 'https://bena.asia/lien-he/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Facebook browser blocker plugin', 'bena-media-helpers' ); ?></a></li>
                </ul>
            </section>

            <section class="bena-sidebar-card bena-sidebar-card--support">
                <header class="bena-sidebar-card__header">
                    <span class="bena-sidebar-card__icon dashicons dashicons-sos"></span>
                    <div>
                        <h3 class="bena-sidebar-card__title"><?php esc_html_e( 'Support', 'bena-media-helpers' ); ?></h3>
                        <p class="bena-sidebar-card__subtitle"><?php esc_html_e( 'Need help? Contact us', 'bena-media-helpers' ); ?></p>
                    </div>
                </header>
                <ul class="bena-sidebar-list">
                    <li><a href="<?php echo esc_url( 'https://bena.asia/lien-he/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Official website', 'bena-media-helpers' ); ?></a></li>
                    <li><a href="<?php echo esc_url( 'https://www.facebook.com/profile.php?id=100091495455637' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Facebook', 'bena-media-helpers' ); ?></a></li>
                    <li><a href="mailto:info@bena.asia"><?php esc_html_e( 'Support email', 'bena-media-helpers' ); ?></a></li>
                </ul>
            </section>

            <section class="bena-sidebar-card bena-sidebar-card--version">
                <header class="bena-sidebar-card__header">
                    <span class="bena-sidebar-card__icon dashicons dashicons-info"></span>
                    <div>
                        <h3 class="bena-sidebar-card__title"><?php esc_html_e( 'Version information', 'bena-media-helpers' ); ?></h3>
                        <p class="bena-sidebar-card__subtitle"><?php esc_html_e( 'Review your plugin details', 'bena-media-helpers' ); ?></p>
                    </div>
                </header>
                <div class="bena-sidebar-meta">
                    <div class="bena-sidebar-meta__row">
                        <span class="bena-sidebar-meta__label"><?php esc_html_e( 'Current version', 'bena-media-helpers' ); ?></span>
                        <span class="bena-sidebar-meta__value"><?php printf( esc_html__( '%s', 'bena-media-helpers' ), esc_html( self::VERSION ) ); ?></span>
                    </div>
                    <div class="bena-sidebar-meta__row">
                        <span class="bena-sidebar-meta__label"><?php esc_html_e( 'Requires WordPress', 'bena-media-helpers' ); ?></span>
                        <span class="bena-sidebar-meta__value"><?php esc_html_e( '5.0+', 'bena-media-helpers' ); ?></span>
                    </div>
                </div>
                <div class="bena-sidebar-rating">
                    <span class="bena-sidebar-rating__label"><?php esc_html_e( 'Rating', 'bena-media-helpers' ); ?></span>
                    <div class="bena-sidebar-rating__stars">
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                    </div>
                </div>
                <div class="bena-sidebar-actions">
                    <a href="<?php echo esc_url( 'https://github.com/benaasia/bena-media-helpers' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                        <?php esc_html_e( 'Rate on GitHub', 'bena-media-helpers' ); ?>
                    </a>
                </div>
            </section>

            <section class="bena-sidebar-card bena-sidebar-card--history">
                <header class="bena-sidebar-card__header">
                    <span class="bena-sidebar-card__icon dashicons dashicons-backup"></span>
                    <div>
                        <h3 class="bena-sidebar-card__title"><?php esc_html_e( 'Change log', 'bena-media-helpers' ); ?></h3>
                        <p class="bena-sidebar-card__subtitle"><?php esc_html_e( 'Track the latest updates', 'bena-media-helpers' ); ?></p>
                    </div>
                </header>
                <div class="bena-sidebar-actions bena-sidebar-actions--stacked">
                    <a href="<?php echo esc_url( 'https://github.com/benaasia/bena-media-helpers/main' ); ?>" target="_blank" rel="noopener noreferrer" class="button">
                        <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                        <?php esc_html_e( 'GitHub', 'bena-media-helpers' ); ?>
                    </a>
                    <a href="<?php echo esc_url( 'https://bena.asia/bena-media-helpers/changelog' ); ?>" target="_blank" rel="noopener noreferrer" class="button">
                        <span class="dashicons dashicons-list-view" aria-hidden="true"></span>
                        <?php esc_html_e( 'Release notes', 'bena-media-helpers' ); ?>
                    </a>
                </div>
            </section>
        </aside>
        <?php
    }

    public function bena_render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap bena-media-settings">
            <div class="bena-media-container">
                <header class="bena-media-header">
                    <div class="bena-media-header__info">
                        <h1><?php esc_html_e( 'Bena Media Helpers', 'bena-media-helpers' ); ?></h1>
                        <p class="bena-media-settings__intro"><?php esc_html_e( 'Manage watermarks and automate image processing effortlessly.', 'bena-media-helpers' ); ?></p>
                    </div>
                    <span class="bena-media-chip"><?php printf( esc_html__( 'Version %s', 'bena-media-helpers' ), esc_html( self::VERSION ) ); ?></span>
                </header>

                <div class="bena-media-layout">
                    <div class="bena-media-main">
                        <form method="post" action="options.php" class="bena-media-form">
                            <?php
                            settings_fields( self::OPTION_KEY );
                            $this->bena_render_feature_toggles();
                            do_settings_sections( self::OPTION_KEY );
                            ?>
                        </form>
                    </div>

                    <?php $this->bena_render_sidebar(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function bena_admin_styles(): void {
        $bena_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $bena_screen || 'settings_page_' . self::PAGE_SLUG !== $bena_screen->id ) {
            return;
        }
        $bena_stylesheet = plugins_url( 'assets/css/bena-admin.css', __FILE__ );

        printf(
            '<link rel="stylesheet" id="bena-media-helpers-admin-css" href="%s" type="text/css" media="all" />',
            esc_url( $bena_stylesheet )
        );
    }

    public function bena_enqueue_assets( string $bena_hook ): void {
        if ( 'settings_page_' . self::PAGE_SLUG !== $bena_hook ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script( 'jquery' );

        $bena_placeholder = esc_html__( 'No watermark selected', 'bena-media-helpers' );
        $bena_use_button  = esc_html__( 'Use this watermark', 'bena-media-helpers' );
        $bena_choose_title = esc_html__( 'Select watermark image', 'bena-media-helpers' );

        $bena_script_handle  = 'bena-media-helpers-admin';
        $bena_script_url     = plugins_url( 'assets/js/bena-admin.js', __FILE__ );
        $bena_script_path    = plugin_dir_path( __FILE__ ) . 'assets/js/bena-admin.js';
        $bena_script_version = file_exists( $bena_script_path ) ? filemtime( $bena_script_path ) : false;

        wp_enqueue_script(
            $bena_script_handle,
            $bena_script_url,
            [ 'jquery' ],
            $bena_script_version ?: false,
            true
        );

        wp_localize_script(
            $bena_script_handle,
            'benaMediaHelpers',
            [
                'placeholderText' => $bena_placeholder,
                'useButton'       => $bena_use_button,
                'chooseTitle'     => $bena_choose_title,
                'optionKey'       => self::OPTION_KEY,
            ]
        );

    }
}

new Bena_Media_Helpers();


/**
* @author Ngoc Tuan
* @description Check Update Bena Media Helpers
*/

require 'bena/plugin-update-checker.php';
$vnhelperUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://wpupdate.bena.asia/?action=get_metadata&slug=bena-media-helpers',
	__FILE__
);

//Here's how you can add query arguments to the URL.
function addBenaMediaHelpersSecretKey($query){
	$query['secret'] = 'bena-media-helpers';
	return $query;
}
$vnhelperUpdateChecker->addQueryArgFilter('addBenaMediaHelpersSecretKey');
?>
