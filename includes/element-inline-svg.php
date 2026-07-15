<?php
/**
 * Cornerstone native Inline SVG element definition.
 *
 * @package EubuleusInlineSVG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$values = cs_compose_values(
	'image',
	'image:link',
	'image:object',
	cs_values( 'aspect-ratio', 'image' ),
	array(
		'svg_source'      => cs_value( '', 'markup', true ),
		'svg_decorative'  => cs_value( false, 'markup:bool', true ),
		'svg_title'       => cs_value( '', 'markup:text', true ),
		'svg_description' => cs_value( '', 'markup:text', true ),
		'svg_link_label'  => cs_value( '', 'markup:text', true ),
	),
	'omega',
	'omega:custom-atts',
	'omega:looper-consumer'
);

/**
 * Reuse Cornerstone's native image and effects style modules.
 *
 * @return array
 */
function eubuleus_inline_svg_tss() {
	return array(
		'modules' => array( 'image', 'effects' ),
	);
}

/**
 * Extend the native image module's inner <img> rules to our inline <svg>.
 *
 * @return string
 */
function eubuleus_inline_svg_style() {
	return <<<'TSS'
&.eub-inline-svg > svg {
  display: block;
  max-width: 100%;
  height: get(image_styled_height);
  max-height: get(image_styled_max_height);
  border-radius: get(image_inner_border_radius);
  object-fit: get(image_object_fit);
  object-position: get(image_object_position);
  aspect-ratio: get(image_aspect_ratio_value);

  @if changed('auto', get(image_styled_width), get-base(image_styled_width)) {
    width: 100%;
  }
}
TSS;
}

/**
 * Build inspector controls with the same design vocabulary as Image.
 *
 * @return array
 */
function eubuleus_inline_svg_builder() {
	$control_font_size = cs_recall( 'control_mixin_font_size', array( 'key' => 'image_font_size' ) );
	$control_width     = cs_recall( 'control_mixin_width', array( 'key' => 'image_styled_width' ) );
	$control_max_width = cs_recall( 'control_mixin_max_width', array( 'key' => 'image_styled_max_width' ) );
	$control_height    = cs_recall( 'control_mixin_height', array( 'key' => 'image_styled_height' ) );
	$control_max_height = cs_recall( 'control_mixin_max_height', array( 'key' => 'image_styled_max_height' ) );
	$control_bg_colors = cs_recall(
		'control_mixin_bg_color_int',
		array(
			'keys' => array(
				'value' => 'image_bg_color',
				'alt'   => 'image_bg_color_alt',
			),
		)
	);

	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'     => 'svg_source',
					'type'    => 'file',
					'label'   => __( 'SVG File', 'eubuleus-inline-svg' ),
					'group'   => 'eub-inline-svg:source',
					'options' => array(
						'file_types' => array( 'image/svg+xml' ),
					),
				),
				array(
					'type'     => 'group',
					'group'    => 'eub-inline-svg:accessibility',
					'controls' => array(
						array(
							'key'     => 'svg_decorative',
							'type'    => 'choose',
							'label'   => __( 'Decorative', 'eubuleus-inline-svg' ),
							'options' => array(
								'choices' => array(
									array( 'value' => false, 'label' => __( 'No', 'eubuleus-inline-svg' ) ),
									array( 'value' => true, 'label' => __( 'Yes', 'eubuleus-inline-svg' ) ),
								),
							),
						),
						array(
							'key'       => 'svg_title',
							'type'      => 'text',
							'label'     => __( 'Accessible Title', 'eubuleus-inline-svg' ),
							'condition' => array( 'svg_decorative' => false ),
							'options'   => array(
								'placeholder' => __( 'Falls back to media alt text', 'eubuleus-inline-svg' ),
							),
						),
						array(
							'key'       => 'svg_description',
							'type'      => 'textarea',
							'label'     => __( 'Description', 'eubuleus-inline-svg' ),
							'condition' => array( 'svg_decorative' => false ),
							'options'   => array( 'height' => 3 ),
						),
						array(
							'key'       => 'svg_link_label',
							'type'      => 'text',
							'label'     => __( 'Link Label', 'eubuleus-inline-svg' ),
							'condition' => array( 'image_link' => true ),
							'options'   => array(
								'placeholder' => __( 'Falls back to the accessible title', 'eubuleus-inline-svg' ),
							),
						),
					),
				),
				array(
					'type'     => 'group',
					'group'    => 'eub-inline-svg:setup',
					'controls' => array(
						$control_font_size,
						array(
							'key'     => 'image_display',
							'type'    => 'choose',
							'label'   => cs_recall( 'label_display' ),
							'options' => cs_recall( 'options_choices_display' ),
						),
						$control_bg_colors,
					),
				),
				array(
					'keys'    => array(
						'url'      => 'image_href',
						'new_tab'  => 'image_blank',
						'nofollow' => 'image_nofollow',
						'toggle'   => 'image_link',
					),
					'type'    => 'link',
					'label'   => __( 'Link', 'eubuleus-inline-svg' ),
					'group'   => 'eub-inline-svg:setup',
					'options' => cs_recall( 'options_group_toggle_off_on_bool' ),
				),
				array(
					'type'     => 'group',
					'group'    => 'eub-inline-svg:size',
					'controls' => array(
						$control_width,
						$control_max_width,
						$control_height,
						$control_max_height,
						cs_partial_controls( 'aspect-ratio', array( 'prefix' => 'image_' ) ),
					),
				),
				cs_control( 'margin', 'image', array( 'group' => 'eub-inline-svg:design' ) ),
				cs_control( 'padding', 'image', array( 'group' => 'eub-inline-svg:design' ) ),
				cs_control(
					'border',
					'image',
					array(
						'group'     => 'eub-inline-svg:design',
						'alt_color' => true,
						'options'   => cs_recall( 'options_color_swatch_base_interaction_labels' ),
					)
				),
				cs_control(
					'border-radius',
					'image_outer',
					array(
						'label_prefix' => __( 'Outer', 'eubuleus-inline-svg' ),
						'group'        => 'eub-inline-svg:design',
					)
				),
				cs_control(
					'border-radius',
					'image_inner',
					array(
						'label_prefix' => __( 'Inner SVG', 'eubuleus-inline-svg' ),
						'group'        => 'eub-inline-svg:design',
					)
				),
				cs_control(
					'box-shadow',
					'image',
					array(
						'group'     => 'eub-inline-svg:design',
						'alt_color' => true,
						'options'   => cs_recall( 'options_color_swatch_base_interaction_labels' ),
					)
				),
			),
			'control_nav' => array(
				'eub-inline-svg'               => __( 'Inline SVG', 'eubuleus-inline-svg' ),
				'eub-inline-svg:source'        => __( 'Source', 'eubuleus-inline-svg' ),
				'eub-inline-svg:accessibility' => __( 'Accessibility', 'eubuleus-inline-svg' ),
				'eub-inline-svg:setup'         => __( 'Setup', 'eubuleus-inline-svg' ),
				'eub-inline-svg:size'          => __( 'Size', 'eubuleus-inline-svg' ),
				'eub-inline-svg:design'        => __( 'Design', 'eubuleus-inline-svg' ),
			),
		),
		cs_partial_controls( 'effects' ),
		cs_partial_controls( 'omega', array( 'add_custom_atts' => true, 'add_looper_consumer' => true ) )
	);
}

$icon = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm1 2v14h14V5H5Zm2.1 10.2 2.7-3.1 2.1 2.4 1.8-2 3.2 3.7H7.1ZM9 10a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/></svg>';

cs_register_element(
	'eubuleus-inline-svg',
	array(
		'title'    => __( 'Inline SVG', 'eubuleus-inline-svg' ),
		'values'   => $values,
		'includes' => array( 'effects' ),
		'builder'  => 'eubuleus_inline_svg_builder',
		'tss'      => 'eubuleus_inline_svg_tss',
		'style'    => 'eubuleus_inline_svg_style',
		'render'   => array( 'Eubuleus_Inline_SVG', 'render_element' ),
		'icon'     => $icon,
		'group'    => 'media',
		'options'  => array(
			'empty_placeholder' => false,
			'label_key'         => 'svg_title',
			'cache'             => false,
		),
	)
);
