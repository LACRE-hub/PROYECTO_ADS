<?php
session_start();
session_destroy();
header('Location: /PROYECTO_ADS/index.html');
exit;
