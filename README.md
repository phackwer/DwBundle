# DwBundle
Simle Data Mining based on views or etl tables. 

Requires SanSIS BaseBundle

Setup your config.yml to map database charset.

If you use MySQL database with latin1 charset, then the real encoding should be set to CP1252.

# Twig Configuration
twig:
    debug:            "%kernel.debug%"
    strict_variables: "%kernel.debug%"
    globals:
        #utilizado pelo DwBundle
        real_database_charset: "%real_database_charset%"