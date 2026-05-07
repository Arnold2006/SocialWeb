-- Migration 037: Custom navigation menu items and About widget link
-- Adds site_settings keys for admin-managed nav items and the About widget link

INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
  ('nav_custom_items',       '[{"label":"SendFile","url":"https:\/\/sf.tera-sat.com","new_tab":1},{"label":"PrintService","url":"https:\/\/print.tera-sat.com","new_tab":1}]'),
  ('about_widget_link_label', 'Why Artnet'),
  ('about_widget_link_url',   'https://artnet.accnet.eu/pages/blog.php?user_id=1&post_id=4');
