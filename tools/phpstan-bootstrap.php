<?php

/**
 * Constantes que o GLPI define em tempo de execução (dependem da instalação)
 * e que o PHPStan precisa conhecer. Usado apenas pela análise estática.
 */
defined('GLPI_PLUGIN_DOC_DIR') || define('GLPI_PLUGIN_DOC_DIR', sys_get_temp_dir());
