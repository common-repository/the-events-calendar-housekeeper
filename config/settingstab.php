<?php
return array(
	'priority' => 40,
	'fields' => array(
		'info-start' => array(
			'type' => 'html',
			'html' => '<div id="modern-tribe-info">'
		),
		'info-box-title' => array(
			'type' => 'html',
			'html' => '<h2>'.__('Housekeeping Settings', 'events-housekeeper').'</h2>'
		),
		'info-box-description' => array(
			'type' => 'html',
			'html' => '<p>'.__('Keep your calendar clean and efficient by scheduling regular garbage collection of expired events. ', 'events-housekeeper')
				.ECHousekeeper::instance()->expiredItemsAlert().' '
                .__('Garbage collection takes place once a day when enabled and, by default, a maximum of 100 events will be collected in a single sweep. ', 'events-housekeeper').'</p>'
				.'<ul><li>'.__('Events are considered to be expired after they have started, <em>not when once they have ended,</em> so you should set the expiry criteria accordingly.', 'events-housekeeper').'</li>'
				.'<li>'.__('The collection will not take place immediately after saving/updating &ndash; there is normally a short delay first of all.', 'events-housekeeper').'</li></ul>'
		),
		'info-end' => array(
			'type' => 'html',
			'html' => '</div>'
		),
		'enableGarbageCollection' => array(
			'type' => 'checkbox_bool',
			'label' => __('Enable garbage collection', 'events-housekeeper'),
			'default' => false,
			'validation_type' => 'boolean',
			'parent_option' => ECHousekeeper::SETTINGS
		),
		'expiryCriteria' => array(
			'type' => 'dropdown',
			'label' => __('Expiry criteria', 'events-housekeeper'),
			'validation_type' => 'options',
			'size' => 'medium',
			'default' => 'draft',
			'options' => array(
				'allExpired' => 'All expired events',
				'oneWeek' => 'Events expired by 1 week or more',
				'twoWeeks' => 'Events expired by 2 weeks or more',
				'oneMonth' => 'Events expired by 1 month or more',
				'sixMonths' => 'Events expired by 6 months or more',
				'oneYear' => 'Events expired by 1 year or more'
			),
			'parent_option' => ECHousekeeper::SETTINGS
)));