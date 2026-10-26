-- Branding global de e-talent: Propuesta C.
-- Se aplica por separado en cada ambiente; no forma parte del rsync de aplicación.
INSERT INTO platform_settings (setting_key, setting_value) VALUES
    ('login_title', 'Metricatest'),
    ('login_subtitle', 'Evalúa el talento. Decide con evidencia.'),
    ('login_logo_path', 'assets/img/branding/metricatest_logo_login_20260915.svg'),
    ('login_background_path', 'assets/img/branding/et-propuesta-c/login-background.png'),
    ('login_form_position', 'right'),
    ('login_button_color', '#2146D0'),
    ('topbar_name', 'Metricatest'),
    ('topbar_icon_path', 'assets/img/branding/metricatest_logo_completo_20260915.svg'),
    ('html_favicon_path', 'assets/img/branding/et-propuesta-c/favicon.svg'),
    ('app_primary_color', '#2146D0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
