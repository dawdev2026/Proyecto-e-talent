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

-- El dominio raíz resuelve la identidad visual de la empresa 4 y sus valores
-- sobrescriben los globales; mantener ambos niveles alineados evita divergencias.
INSERT INTO company_branding (company_id, setting_key, setting_value) VALUES
    (4, 'login_title', 'Metricatest'),
    (4, 'login_subtitle', 'Evalúa el talento. Decide con evidencia.'),
    (4, 'login_logo_path', 'assets/img/branding/metricatest_logo_login_20260915.svg'),
    (4, 'login_background_path', 'assets/img/branding/et-propuesta-c/login-background.png'),
    (4, 'login_form_position', 'right'),
    (4, 'login_button_color', '#2146D0'),
    (4, 'topbar_name', 'Metricatest'),
    (4, 'topbar_icon_path', 'assets/img/branding/metricatest_logo_completo_20260915.svg'),
    (4, 'html_favicon_path', 'assets/img/branding/et-propuesta-c/favicon.svg'),
    (4, 'app_primary_color', '#2146D0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
