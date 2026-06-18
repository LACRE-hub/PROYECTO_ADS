
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
DROP TABLE IF EXISTS REASIGNACION_PACIENTE;
DROP TABLE IF EXISTS LOG_AUDITORIA;
DROP TABLE IF EXISTS ALERTA_MANTENIMIENTO;
DROP TABLE IF EXISTS MANTENIMIENTO;
DROP TABLE IF EXISTS MODIFICACION_PAGO;
DROP TABLE IF EXISTS PAGO_DETALLE;
DROP TABLE IF EXISTS PAGO;
DROP TABLE IF EXISTS ALERTA_STOCK;
DROP TABLE IF EXISTS MOVIMIENTO_INSUMO;
DROP TABLE IF EXISTS EQUIPO_MEDICO;
DROP TABLE IF EXISTS INSUMO;
DROP TABLE IF EXISTS NOTA_ENFERMERIA;
DROP TABLE IF EXISTS HOSPITALIZACION;
DROP TABLE IF EXISTS SECUENCIA_CAMA;
DROP TABLE IF EXISTS CAMA;
DROP TABLE IF EXISTS DESPACHO_FARMACIA;
DROP TABLE IF EXISTS RECETA_DETALLE;
DROP TABLE IF EXISTS RECETA;
DROP TABLE IF EXISTS LOTE_MEDICAMENTO;
DROP TABLE IF EXISTS MEDICAMENTO;
DROP TABLE IF EXISTS REFERENCIA_ESPECIALISTA;
DROP TABLE IF EXISTS RESULTADO_ESTUDIO;
DROP TABLE IF EXISTS ORDEN_ESTUDIO;
DROP TABLE IF EXISTS SIGNOS_VITALES;
DROP TABLE IF EXISTS CONSULTA;
DROP TABLE IF EXISTS CITA;
DROP TABLE IF EXISTS EXPEDIENTE_CLINICO;
DROP TABLE IF EXISTS CONTACTO_PACIENTE;
DROP TABLE IF EXISTS PACIENTE;
DROP TABLE IF EXISTS DIRECCION_PACIENTE;
DROP TABLE IF EXISTS TIPO_EMPLEADO;
DROP TABLE IF EXISTS EMPLEADO;
DROP TABLE IF EXISTS CONSULTORIO;
DROP TABLE IF EXISTS PERMISO_ROL;
DROP TABLE IF EXISTS ROL;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE ROL (
    id_rol       INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_rol   VARCHAR(60)  NOT NULL,
    descripcion  VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE PERMISO_ROL (
    id_permiso    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_rol        INT          NOT NULL,
    modulo        VARCHAR(80)  NOT NULL,
    nivel_acceso  VARCHAR(40)  NOT NULL,
    CONSTRAINT fk_permiso_rol FOREIGN KEY (id_rol) REFERENCES ROL(id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE CONSULTORIO (
    id_consultorio      INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero_consultorio  INT         NOT NULL,
    area                VARCHAR(80) NOT NULL,
    estatus             VARCHAR(40) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE EMPLEADO (
    id_empleado           INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero_empleado       VARCHAR(10)  NOT NULL UNIQUE,
    nombre                VARCHAR(80)  NOT NULL,
    apellido_paterno      VARCHAR(80)  NOT NULL,
    apellido_materno      VARCHAR(80),
    tipo                  VARCHAR(60)  NOT NULL,
    turno                 VARCHAR(30),
    area_asignada         VARCHAR(80),
    estatus               VARCHAR(30)  NOT NULL DEFAULT 'Activo',
    fecha_baja_programada DATE,
    fecha_separacion      DATE,
    motivo_baja           VARCHAR(255),
    contrasena_hash       VARCHAR(255) NOT NULL,
    id_rol                INT          NOT NULL,
    id_consultorio        INT,
    CONSTRAINT fk_emp_rol         FOREIGN KEY (id_rol)         REFERENCES ROL(id_rol),
    CONSTRAINT fk_emp_consultorio FOREIGN KEY (id_consultorio) REFERENCES CONSULTORIO(id_consultorio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE TIPO_EMPLEADO (
    id_tipo_empleado   INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_empleado        INT          NOT NULL UNIQUE,
    cedula_profesional VARCHAR(20),
    especialidad       VARCHAR(80),
    subespecialidad    VARCHAR(80),
    CONSTRAINT fk_tipo_emp FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE LOG_AUDITORIA (
    id_log         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario     INT          NOT NULL,
    modulo         VARCHAR(60)  NOT NULL,
    accion         VARCHAR(120) NOT NULL,
    fecha_hora     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_equipo      VARCHAR(45),
    detalle_cambio TEXT,
    CONSTRAINT fk_log_usuario FOREIGN KEY (id_usuario) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE MANTENIMIENTO (
    id_mantenimiento  INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_administrador  INT         NOT NULL,
    fecha_hora_inicio DATETIME    NOT NULL,
    duracion_minutos  INT         NOT NULL,
    descripcion       TEXT,
    estatus           VARCHAR(30) NOT NULL DEFAULT 'Programado',
    CONSTRAINT fk_mant_admin FOREIGN KEY (id_administrador) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ALERTA_MANTENIMIENTO (
    id_alerta        INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_mantenimiento INT         NOT NULL,
    area_notificada  VARCHAR(80) NOT NULL,
    fecha_envio      DATETIME    NOT NULL,
    estatus_envio    VARCHAR(30) NOT NULL DEFAULT 'Enviado',
    CONSTRAINT fk_alerta_mant FOREIGN KEY (id_mantenimiento) REFERENCES MANTENIMIENTO(id_mantenimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE DIRECCION_PACIENTE (
    id_direccion  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    calle         VARCHAR(120) NOT NULL,
    numero        VARCHAR(20),
    colonia       VARCHAR(80),
    codigo_postal CHAR(5),
    ciudad        VARCHAR(80)  NOT NULL,
    estado        VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE PACIENTE (
    id_paciente         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero_expediente   VARCHAR(10)  NOT NULL UNIQUE,
    nombre              VARCHAR(80)  NOT NULL,
    apellido_paterno    VARCHAR(80)  NOT NULL,
    apellido_materno    VARCHAR(80),
    fecha_nacimiento    DATE         NOT NULL,
    sexo                CHAR(1)      NOT NULL,
    curp                CHAR(18)     UNIQUE,
    correo_electronico  VARCHAR(120),
    id_direccion        INT,
    CONSTRAINT fk_pac_dir  FOREIGN KEY (id_direccion) REFERENCES DIRECCION_PACIENTE(id_direccion),
    CONSTRAINT chk_sexo    CHECK (sexo IN ('M','F'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE CONTACTO_PACIENTE (
    id_contacto     INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente     INT         NOT NULL,
    nombre_contacto VARCHAR(120) NOT NULL,
    telefono        VARCHAR(15),
    parentesco      VARCHAR(40),
    es_emergencia   TINYINT(1)  NOT NULL DEFAULT 0,
    orden           INT         NOT NULL DEFAULT 1,
    CONSTRAINT fk_cont_pac FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE EXPEDIENTE_CLINICO (
    id_expediente         INT  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente           INT  NOT NULL UNIQUE,
    grupo_sanguineo       VARCHAR(5),
    alergias              TEXT,
    enfermedades_cronicas TEXT,
    fecha_apertura        DATE NOT NULL DEFAULT (CURRENT_DATE),
    CONSTRAINT fk_exp_pac FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE CITA (
    id_cita               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente           INT          NOT NULL,
    id_medico             INT          NOT NULL,
    fecha_hora            DATETIME     NOT NULL,
    fecha_hora_original   DATETIME,
    motivo_reagendamiento VARCHAR(255),
    motivo_consulta       VARCHAR(255),
    estatus               VARCHAR(30)  NOT NULL DEFAULT 'Pendiente',
    tipo_cita             VARCHAR(50),
    CONSTRAINT fk_cita_pac    FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente),
    CONSTRAINT fk_cita_medico FOREIGN KEY (id_medico)   REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE CONSULTA (
    id_consulta  INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cita      INT      NOT NULL UNIQUE,
    id_medico    INT      NOT NULL,
    id_paciente  INT      NOT NULL,
    diagnostico  TEXT,
    tratamiento  TEXT,
    notas        TEXT,
    fecha_hora   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_con_cita    FOREIGN KEY (id_cita)      REFERENCES CITA(id_cita),
    CONSTRAINT fk_con_medico  FOREIGN KEY (id_medico)    REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_con_pac     FOREIGN KEY (id_paciente)  REFERENCES PACIENTE(id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE SIGNOS_VITALES (
    id_registro             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente             INT           NOT NULL,
    id_empleado             INT           NOT NULL,
    fecha_hora              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tension_arterial        VARCHAR(10),
    temperatura             DECIMAL(4,1),
    frecuencia_cardiaca     INT,
    frecuencia_respiratoria INT,
    peso_kg                 DECIMAL(5,2),
    talla_cm                DECIMAL(5,2),
    saturacion_oxigeno      DECIMAL(4,1),
    CONSTRAINT fk_sv_pac  FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente),
    CONSTRAINT fk_sv_emp  FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ORDEN_ESTUDIO (
    id_orden        INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_consulta     INT          NOT NULL,
    id_medico       INT          NOT NULL,
    id_paciente     INT          NOT NULL,
    tipo_estudio    VARCHAR(60)  NOT NULL,
    nombre_estudio  VARCHAR(120) NOT NULL,
    fecha_solicitud DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estatus         VARCHAR(40)  NOT NULL DEFAULT 'Pendiente',
    CONSTRAINT fk_oe_con  FOREIGN KEY (id_consulta) REFERENCES CONSULTA(id_consulta),
    CONSTRAINT fk_oe_med  FOREIGN KEY (id_medico)   REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_oe_pac  FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE RESULTADO_ESTUDIO (
    id_resultado       INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_orden           INT          NOT NULL UNIQUE,
    id_tecnico         INT          NOT NULL,
    resultado          TEXT,
    ruta_archivo       VARCHAR(255),
    fecha_captura      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estatus            VARCHAR(40)  NOT NULL DEFAULT 'Capturado',
    requiere_impresion TINYINT(1)   NOT NULL DEFAULT 0,
    CONSTRAINT fk_re_orden   FOREIGN KEY (id_orden)   REFERENCES ORDEN_ESTUDIO(id_orden),
    CONSTRAINT fk_re_tecnico FOREIGN KEY (id_tecnico) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE REFERENCIA_ESPECIALISTA (
    id_referencia        INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_consulta          INT          NOT NULL,
    id_medico_remite     INT          NOT NULL,
    id_paciente          INT          NOT NULL,
    especialidad_destino VARCHAR(80)  NOT NULL,
    motivo_referencia    TEXT,
    notas_clinicas       TEXT,
    fecha_emision        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estatus              VARCHAR(30)  NOT NULL DEFAULT 'Pendiente',
    CONSTRAINT fk_ref_con  FOREIGN KEY (id_consulta)      REFERENCES CONSULTA(id_consulta),
    CONSTRAINT fk_ref_med  FOREIGN KEY (id_medico_remite) REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_ref_pac  FOREIGN KEY (id_paciente)      REFERENCES PACIENTE(id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE MEDICAMENTO (
    id_medicamento    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_sustancia  VARCHAR(120) NOT NULL,
    nombre_comercial  VARCHAR(120),
    presentacion      VARCHAR(80),
    tipo_medicamento  VARCHAR(60),
    stock_minimo      INT          NOT NULL DEFAULT 0,
    dias_dispensacion VARCHAR(60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE LOTE_MEDICAMENTO (
    id_lote                 INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_medicamento          INT           NOT NULL,
    numero_lote             VARCHAR(40)   NOT NULL,
    existencia_actual       INT           NOT NULL DEFAULT 0,
    fecha_caducidad         DATE          NOT NULL,
    fecha_recepcion         DATE          NOT NULL,
    precio_propuesto        DECIMAL(10,2),
    precio_vigente          DECIMAL(10,2),
    estatus_precio          VARCHAR(30)   NOT NULL DEFAULT 'Vigente',
    id_farmaceutico         INT           NOT NULL,
    id_administrador_valida INT,
    CONSTRAINT fk_lote_med   FOREIGN KEY (id_medicamento)          REFERENCES MEDICAMENTO(id_medicamento),
    CONSTRAINT fk_lote_farm  FOREIGN KEY (id_farmaceutico)         REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_lote_admin FOREIGN KEY (id_administrador_valida) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE RECETA (
    id_receta     INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_consulta   INT         NOT NULL,
    id_medico     INT         NOT NULL,
    id_paciente   INT         NOT NULL,
    tipo_receta   VARCHAR(30) NOT NULL DEFAULT 'Normal',
    fecha_emision DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rec_con  FOREIGN KEY (id_consulta) REFERENCES CONSULTA(id_consulta),
    CONSTRAINT fk_rec_med  FOREIGN KEY (id_medico)   REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_rec_pac  FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE RECETA_DETALLE (
    id_detalle     INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_receta      INT         NOT NULL,
    id_medicamento INT         NOT NULL,
    dosis          VARCHAR(60),
    indicaciones   TEXT,
    cantidad       INT         NOT NULL DEFAULT 1,
    CONSTRAINT fk_rd_rec FOREIGN KEY (id_receta)      REFERENCES RECETA(id_receta),
    CONSTRAINT fk_rd_med FOREIGN KEY (id_medicamento) REFERENCES MEDICAMENTO(id_medicamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE DESPACHO_FARMACIA (
    id_despacho     INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_receta       INT           NOT NULL,
    id_lote         INT           NOT NULL,
    id_farmaceutico INT           NOT NULL,
    cantidad        INT           NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    fecha_despacho  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_df_rec  FOREIGN KEY (id_receta)       REFERENCES RECETA(id_receta),
    CONSTRAINT fk_df_lote FOREIGN KEY (id_lote)         REFERENCES LOTE_MEDICAMENTO(id_lote),
    CONSTRAINT fk_df_farm FOREIGN KEY (id_farmaceutico) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE PAGO (
    id_pago       INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente   INT            NOT NULL,
    id_cajero     INT            NOT NULL,
    folio_pago    CHAR(12)       NOT NULL UNIQUE,
    monto_total   DECIMAL(12,2)  NOT NULL,
    metodo_pago   VARCHAR(40)    NOT NULL,
    fecha_hora    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estatus_pago  VARCHAR(30)    NOT NULL DEFAULT 'Pendiente',
    CONSTRAINT fk_pago_pac    FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente),
    CONSTRAINT fk_pago_cajero FOREIGN KEY (id_cajero)   REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE PAGO_DETALLE (
    id_detalle_pago               INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pago                       INT           NOT NULL,
    tipo_concepto                 VARCHAR(40)   NOT NULL,
    id_referencia_consulta        INT,
    id_referencia_estudio         INT,
    id_referencia_despacho        INT,
    id_referencia_hospitalizacion INT,
    monto                         DECIMAL(12,2) NOT NULL,
    descripcion                   VARCHAR(255),
    CONSTRAINT fk_pd_pago     FOREIGN KEY (id_pago)                  REFERENCES PAGO(id_pago),
    CONSTRAINT fk_pd_consulta FOREIGN KEY (id_referencia_consulta)   REFERENCES CONSULTA(id_consulta),
    CONSTRAINT fk_pd_estudio  FOREIGN KEY (id_referencia_estudio)    REFERENCES ORDEN_ESTUDIO(id_orden),
    CONSTRAINT fk_pd_despacho FOREIGN KEY (id_referencia_despacho)   REFERENCES DESPACHO_FARMACIA(id_despacho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE MODIFICACION_PAGO (
    id_modificacion    INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pago            INT         NOT NULL,
    id_solicitante     INT         NOT NULL,
    id_administrador   INT         NOT NULL,
    motivo             TEXT,
    fecha_solicitud    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_autorizacion DATETIME,
    estatus            VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    CONSTRAINT fk_mp_pago  FOREIGN KEY (id_pago)          REFERENCES PAGO(id_pago),
    CONSTRAINT fk_mp_sol   FOREIGN KEY (id_solicitante)   REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_mp_admin FOREIGN KEY (id_administrador) REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE CAMA (
    id_cama        INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero_cama    INT         NOT NULL,
    tipo           VARCHAR(40) NOT NULL,
    area           VARCHAR(80) NOT NULL,
    estatus        VARCHAR(30) NOT NULL DEFAULT 'Disponible',
    id_consultorio INT,
    CONSTRAINT fk_cama_con FOREIGN KEY (id_consultorio) REFERENCES CONSULTORIO(id_consultorio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE SECUENCIA_CAMA (
    id_secuencia        INT  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contador_global     INT  NOT NULL DEFAULT 0,
    fecha_actualizacion DATE NOT NULL DEFAULT (CURRENT_DATE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE HOSPITALIZACION (
    id_hospitalizacion    INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente           INT           NOT NULL,
    id_medico             INT           NOT NULL,
    id_cama               INT           NOT NULL,
    fecha_ingreso         DATETIME      NOT NULL,
    fecha_egreso          DATETIME,
    costo_diario          DECIMAL(10,2) NOT NULL,
    numero_asignacion     INT,
    estatus_limpieza_cama VARCHAR(20)   NOT NULL DEFAULT 'Limpia',
    CONSTRAINT fk_hosp_pac   FOREIGN KEY (id_paciente) REFERENCES PACIENTE(id_paciente),
    CONSTRAINT fk_hosp_med   FOREIGN KEY (id_medico)   REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_hosp_cama  FOREIGN KEY (id_cama)     REFERENCES CAMA(id_cama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE NOTA_ENFERMERIA (
    id_nota            INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_paciente        INT         NOT NULL,
    id_empleado        INT         NOT NULL,
    id_hospitalizacion INT         NOT NULL,
    tipo_cuidado       VARCHAR(80),
    descripcion        TEXT,
    fecha_hora         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ne_pac   FOREIGN KEY (id_paciente)        REFERENCES PACIENTE(id_paciente),
    CONSTRAINT fk_ne_emp   FOREIGN KEY (id_empleado)        REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_ne_hosp  FOREIGN KEY (id_hospitalizacion) REFERENCES HOSPITALIZACION(id_hospitalizacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE INSUMO (
    id_insumo       INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_articulo VARCHAR(120) NOT NULL,
    categoria       VARCHAR(60)  NOT NULL,
    stock_actual    INT          NOT NULL DEFAULT 0,
    stock_minimo    INT          NOT NULL DEFAULT 0,
    area_asignada   VARCHAR(80)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE EQUIPO_MEDICO (
    id_equipo                  INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_insumo                  INT         NOT NULL UNIQUE,
    estatus_equipo             VARCHAR(30) NOT NULL DEFAULT 'Operativo',
    fecha_ultimo_mantenimiento DATE,
    proxima_revision           DATE,
    CONSTRAINT fk_eq_insumo FOREIGN KEY (id_insumo) REFERENCES INSUMO(id_insumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE MOVIMIENTO_INSUMO (
    id_movimiento   INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_insumo       INT         NOT NULL,
    id_empleado     INT         NOT NULL,
    tipo_movimiento VARCHAR(20) NOT NULL,
    cantidad        INT         NOT NULL,
    motivo          VARCHAR(255),
    fecha_hora      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mi_insumo  FOREIGN KEY (id_insumo)   REFERENCES INSUMO(id_insumo),
    CONSTRAINT fk_mi_emp     FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT chk_tipo_mov  CHECK (tipo_movimiento IN ('Entrada','Salida'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE ALERTA_STOCK (
    id_alerta        INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_insumo        INT         NOT NULL,
    tipo_origen      VARCHAR(60),
    area             VARCHAR(80),
    stock_al_momento INT,
    fecha_generacion DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estatus          VARCHAR(30) NOT NULL DEFAULT 'Activa',
    CONSTRAINT fk_as_insumo FOREIGN KEY (id_insumo) REFERENCES INSUMO(id_insumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE REASIGNACION_PACIENTE (
    id_reasignacion      INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_empleado_saliente INT      NOT NULL,
    id_empleado_receptor INT      NOT NULL,
    id_paciente          INT      NOT NULL,
    fecha_reasignacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_administrador     INT      NOT NULL,
    CONSTRAINT fk_rp_sal   FOREIGN KEY (id_empleado_saliente) REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_rp_rec   FOREIGN KEY (id_empleado_receptor) REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT fk_rp_pac   FOREIGN KEY (id_paciente)          REFERENCES PACIENTE(id_paciente),
    CONSTRAINT fk_rp_admin FOREIGN KEY (id_administrador)     REFERENCES EMPLEADO(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO ROL (nombre_rol, descripcion) VALUES
('Administrador', 'Acceso total al sistema'),
('MÃ©dico',        'Consulta, prescripciÃ³n y referencia'),
('Enfermero',     'Registro de signos vitales y notas de enfermerÃ­a'),
('FarmacÃ©utico',  'GestiÃ³n de lotes y despacho de medicamentos'),
('Cajero',        'Registro y cobro de pagos'),
('TÃ©cnico Lab',   'Captura de resultados de estudios'),
('Paciente',      'Consulta de prÃ³xima cita â€” acceso mÃ­nimo');
INSERT INTO PERMISO_ROL (id_rol, modulo, nivel_acceso) VALUES
(1, 'Sistema',        'total'),
(2, 'Consulta',       'lectura/escritura'),
(2, 'Receta',         'lectura/escritura'),
(2, 'Cita',           'lectura/escritura'),
(3, 'SignosVitales',  'lectura/escritura'),
(3, 'NotaEnfermeria', 'lectura/escritura'),
(4, 'Farmacia',       'lectura/escritura'),
(5, 'Pagos',          'lectura/escritura'),
(6, 'Estudios',       'lectura/escritura'),
(7, 'Cita',           'solo lectura');
INSERT INTO CONSULTORIO (numero_consultorio, area, estatus) VALUES
(101, 'Medicina General',  'Activo'),
(102, 'CardiologÃ­a',       'Activo'),
(103, 'PediatrÃ­a',         'Activo'),
(201, 'Urgencias',         'Activo'),
(202, 'GinecologÃ­a',       'En mantenimiento'),
(203, 'NeurologÃ­a',        'Activo'),
(301, 'Ortopedia',         'Activo');
INSERT INTO EMPLEADO (numero_empleado, nombre, apellido_paterno, apellido_materno,
  tipo, turno, area_asignada, estatus, fecha_baja_programada, fecha_separacion,
  motivo_baja, contrasena_hash, id_rol, id_consultorio) VALUES
('0000000001', 'Carlos',   'RamÃ­rez',   'LÃ³pez',     'Administrador', 'Matutino',   'DirecciÃ³n',        'Activo', NULL,         NULL,         NULL,                  '$2y$12$/gVj2/QGA7sECFGdspNCg.hB0CdJAPVNK5ayGY3eiigXJ.sUd8YhC', 1, NULL),
('0000000002', 'Ana',      'GonzÃ¡lez',  'MartÃ­nez',  'MÃ©dico',        'Matutino',   'Medicina General', 'Activo', NULL,         NULL,         NULL,                  '$2y$12$J5dUc/GCuHc.6MwETncsKeMVofNANYTnv0oVl6KBwgv9dvZf.q4wu', 2, 1),
('0000000003', 'Jorge',    'HernÃ¡ndez', 'Ruiz',      'MÃ©dico',        'Vespertino', 'CardiologÃ­a',      'Activo', NULL,         NULL,         NULL,                  '$2y$12$45rReI/A.a9aVpxlBft/u.M7G5sPy7pZCv7.yEZN5UFdLnZiGOSki', 2, 2),
('0000000004', 'SofÃ­a',    'Torres',    'Vega',      'Enfermero',     'Matutino',   'HospitalizaciÃ³n',  'Activo', NULL,         NULL,         NULL,                  '$2y$12$YwPQgxFA.i8wOHWgvvGuBeyjD90cNwokHl49yR1Z8YJEYA5qFPDJy', 3, NULL),
('0000000005', 'Luis',     'Castillo',  'Mora',      'FarmacÃ©utico',  'Matutino',   'Farmacia',         'Activo', NULL,         NULL,         NULL,                  '$2y$12$1FsIGuEUozwrZZhnoz2t6uS4H5M8956AHXwPD/5cI4vrxASQLEYZS', 4, NULL),
('0000000006', 'Mariana',  'Flores',    'RÃ­os',      'Cajero',        'Matutino',   'Caja',             'Activo', NULL,         NULL,         NULL,                  '$2y$12$1VKE6JwDZXydzKZcnGpsq..mWUtvl5FNkDgDfHe8yU0bMgScvMJxe', 5, NULL),
('0000000007', 'Pedro',    'Vargas',    'Salinas',   'TÃ©cnico Lab',   'Matutino',   'Laboratorio',      'Activo', NULL,         NULL,         NULL,                  '$2y$12$Ast06hib4Kl4.zdtrTuU3OMTbO0iicNoPZGPygilwzJD58RCONnMG', 6, NULL),
('0000000008', 'Fernanda', 'Medina',    'Cruz',      'MÃ©dico',        'Nocturno',   'PediatrÃ­a',        'Activo', NULL,         NULL,         NULL,                  '$2y$12$.Q4WOb4xuAT/tQf.wod/jeqpkb50CYdXkMwAtzx.NKT6B9CV0ccvS', 2, 3),
('0000000009', 'Roberto',  'JimÃ©nez',   'Ponce',     'Enfermero',     'Vespertino', 'Urgencias',        'Activo', NULL,         NULL,         NULL,                  '$2y$12$BTuGlhpt9TVWqZNVjOQFZOJuaiAH6haWTTwu3b3M68CcYR6YIPm8a', 3, NULL),
('0000000010', 'Gabriela', 'Soto',      'MÃ©ndez',    'FarmacÃ©utico',  'Vespertino', 'Farmacia',         'Baja',   '2025-12-31', '2025-12-31', 'Renuncia voluntaria', '$2y$12$LUKWwWJZxJrm8sluzpV2veFWd0Yufqkwxx8FGtQ9VJI8ZoPCm/yLG', 4, NULL),
('0000000011', 'Diego',    'Reyes',     'Acosta',    'MÃ©dico',        'Matutino',   'NeurologÃ­a',       'Activo', NULL,         NULL,         NULL,                  '$2y$12$h7Dydg6uQgHhbjpyvGeO/eaNm.CPyBnzanu90hfb9WD7q1UpBMGaS', 2, 6),
('0000000012', 'Valeria',  'GutiÃ©rrez', 'Paredes',   'MÃ©dico',        'Matutino',   'Ortopedia',        'Activo', NULL,         NULL,         NULL,                  '$2y$12$nZ3YS46tYngs1gt0Rek5guht/ZLNh586E82oD6gtEnSkvaJd5sP9m', 2, 7),
('0000000013', 'HÃ©ctor',   'Morales',   'IbÃ¡Ã±ez',    'TÃ©cnico Lab',   'Vespertino', 'Laboratorio',      'Activo', NULL,         NULL,         NULL,                  '$2y$12$jP7jGOZY5LY32SoApZ9EMe3FfNbOPz6DueFyjghKHKH2xIpgbyYwK', 6, NULL),
('0000000014', 'Patricia', 'Luna',      'Serrano',   'Cajero',        'Vespertino', 'Caja',             'Activo', NULL,         NULL,         NULL,                  '$2y$12$ayZW7xarVtUNu5RzrnBAI.P7eCPFCIz/baIHS321q61QRIlb9KnYW', 5, NULL),
('0000000015', 'AndrÃ©s',   'PeÃ±a',      'DomÃ­nguez', 'Enfermero',     'Nocturno',   'HospitalizaciÃ³n',  'Activo', NULL,         NULL,         NULL,                  '$2y$12$.9AhGPy0kuzlrKmLl3SVqe.zuciGjoxS35AhyHv/ZpaxfFihF4RXS', 3, NULL);
INSERT INTO TIPO_EMPLEADO (id_empleado, cedula_profesional, especialidad, subespecialidad) VALUES
(2,  'CED-1234567', 'Medicina General', NULL),
(3,  'CED-2345678', 'CardiologÃ­a',      'ElectrofisiologÃ­a'),
(8,  'CED-3456789', 'PediatrÃ­a',        NULL),
(11, 'CED-4567890', 'NeurologÃ­a',       'NeurologÃ­a Vascular'),
(12, 'CED-5678901', 'Ortopedia',        'CirugÃ­a de Columna');
INSERT INTO DIRECCION_PACIENTE (calle, numero, colonia, codigo_postal, ciudad, estado) VALUES
('Av. Insurgentes Sur',       '1234', 'Del Valle',        '03100', 'Ciudad de MÃ©xico', 'CDMX'),
('Calle Hidalgo',             '56',   'Centro HistÃ³rico', '06060', 'Ciudad de MÃ©xico', 'CDMX'),
('Blvd. Tlalpan',             '890',  'Pedregal',         '14200', 'Ciudad de MÃ©xico', 'CDMX'),
('Calle Morelos',             '12',   'Santa MarÃ­a',      '44600', 'Guadalajara',      'Jalisco'),
('Av. JuÃ¡rez',                '77',   'Col. ObregÃ³n',     '64000', 'Monterrey',        'Nuevo LeÃ³n'),
('Calle 5 de Mayo',           '321',  'Centro',           '72000', 'Puebla',           'Puebla'),
('Blvd. Adolfo LÃ³pez Mateos', '450',  'Las Fuentes',      '22010', 'Tijuana',          'Baja California'),
('Av. Chapultepec',           '900',  'Roma Norte',       '06760', 'Ciudad de MÃ©xico', 'CDMX');
INSERT INTO PACIENTE (numero_expediente, nombre, apellido_paterno, apellido_materno,
  fecha_nacimiento, sexo, curp, correo_electronico, id_direccion) VALUES
('0000000001', 'MarÃ­a',     'LÃ³pez',   'Fuentes',  '1985-03-12', 'F', 'LOFM850312MDFPXR01', 'maria.lopez@correo.com',   1),
('0000000002', 'Juan',      'PÃ©rez',   'SÃ¡nchez',  '1978-07-25', 'M', 'PESJ780725HDFRNQ02', 'juan.perez@correo.com',    2),
('0000000003', 'Elena',     'GarcÃ­a',  'Morales',  '1992-11-04', 'F', 'GAME921104MDFRCL03', 'elena.garcia@correo.com',  3),
('0000000004', 'Miguel',    'Torres',  'RamÃ­rez',  '2005-01-18', 'M', 'TORM050118HDFRNL04', 'tutor.torres@correo.com',  4),
('0000000005', 'Carmen',    'Ruiz',    'Castro',   '1960-09-30', 'F', 'RUCC600930MDFZST05', 'carmen.ruiz@correo.com',   5),
('0000000006', 'Alejandro', 'Navarro', 'Espinoza', '1990-06-15', 'M', 'NAEE900615HDFSVR06', 'alex.navarro@correo.com',  6),
('0000000007', 'LucÃ­a',     'Mendoza', 'Ortega',   '2001-02-28', 'F', 'MEOL010228MDFNRT07', 'lucia.mendoza@correo.com', 7),
('0000000008', 'Ricardo',   'Ãvila',   'Fuentes',  '1955-12-10', 'M', 'AVFR551210HDFLNS08', 'ricardo.avila@correo.com', 8);
INSERT INTO CONTACTO_PACIENTE (id_paciente, nombre_contacto, telefono, parentesco, es_emergencia, orden) VALUES
(1, 'Pedro LÃ³pez Fuentes',   '5551234567', 'Hermano', 1, 1),
(1, 'Rosa Fuentes de LÃ³pez', '5557654321', 'Madre',   0, 2),
(2, 'Clara SÃ¡nchez Vidal',   '5552345678', 'Esposa',  1, 1),
(3, 'Luis GarcÃ­a Morales',   '5553456789', 'Padre',   1, 1),
(4, 'Susana RamÃ­rez DÃ­az',   '5554567890', 'Madre',   1, 1),
(5, 'Antonio Ruiz Castro',   '5555678901', 'Hijo',    1, 1),
(6, 'Gloria Espinoza Vega',  '5556789012', 'Madre',   1, 1),
(7, 'Carlos Ortega RÃ­os',    '5557890123', 'Padre',   1, 1),
(8, 'Silvia Fuentes Mora',   '5558901234', 'Esposa',  1, 1),
(8, 'Jorge Ãvila Fuentes',   '5559012345', 'Hijo',    0, 2);
INSERT INTO EXPEDIENTE_CLINICO (id_paciente, grupo_sanguineo, alergias, enfermedades_cronicas, fecha_apertura) VALUES
(1, 'O+',  'Penicilina',             'HipertensiÃ³n arterial',                      '2020-01-15'),
(2, 'A+',  'Ninguna conocida',        'Diabetes mellitus tipo 2',                   '2019-06-10'),
(3, 'B-',  'Aspirina, Sulfonamidas',  'Ninguna',                                    '2023-03-22'),
(4, 'AB+', 'Ninguna conocida',        'Asma leve',                                  '2022-09-05'),
(5, 'O-',  'LÃ¡tex',                   'Artritis reumatoide, Hipotiroidismo',         '2018-04-18'),
(6, 'A-',  'Ibuprofeno',              'Gastritis crÃ³nica',                           '2024-01-20'),
(7, 'B+',  'Ninguna conocida',        'Ninguna',                                    '2024-07-11'),
(8, 'O+',  'Contraste yodado',        'Insuficiencia renal crÃ³nica estadio 3, HTA', '2015-08-30');
INSERT INTO CITA (id_paciente, id_medico, fecha_hora, fecha_hora_original,
  motivo_reagendamiento, motivo_consulta, estatus, tipo_cita) VALUES
(1, 2,  '2026-04-08 09:00:00', '2026-04-08 09:00:00', NULL,                    'Control de presiÃ³n arterial',         'Completada',  'Control'),
(2, 2,  '2026-04-10 10:30:00', '2026-04-10 10:30:00', NULL,                    'RevisiÃ³n de glucosa y HbA1c',         'Completada',  'Control'),
(3, 3,  '2026-04-15 11:00:00', '2026-04-08 11:00:00', 'Paciente fuera de CDMX','Dolor en el pecho al esfuerzo',       'Completada',  'Primera vez'),
(4, 8,  '2026-04-20 08:00:00', '2026-04-20 08:00:00', NULL,                    'Crisis asmÃ¡tica leve',                'Completada',  'Urgencia'),
(5, 2,  '2026-05-05 09:30:00', '2026-05-05 09:30:00', NULL,                    'Dolor articular en manos y rodillas', 'Completada',  'Control'),
(1, 3,  '2026-05-12 10:00:00', '2026-05-12 10:00:00', NULL,                    'EvaluaciÃ³n cardiolÃ³gica preventiva',  'Completada',  'Especialidad'),
(6, 2,  '2026-05-18 11:00:00', '2026-05-18 11:00:00', NULL,                    'Dolor epigÃ¡strico frecuente',         'Completada',  'Primera vez'),
(7, 8,  '2026-05-22 09:00:00', '2026-05-22 09:00:00', NULL,                    'RevisiÃ³n general anual',              'Completada',  'Control'),
(8, 11, '2026-05-25 10:00:00', '2026-05-25 10:00:00', NULL,                    'Cefalea intensa recurrente',          'Completada',  'Primera vez'),
(2, 2,  '2026-06-10 10:30:00', '2026-06-10 10:30:00', NULL,                    'Control diabetes â€” 2 meses',          'Pendiente',   'Control'),
(5, 12, '2026-06-12 09:00:00', '2026-06-12 09:00:00', NULL,                    'ValoraciÃ³n articulaciones',           'Pendiente',   'Especialidad'),
(3, 3,  '2026-06-18 11:30:00', '2026-06-11 11:30:00', 'MÃ©dico con guardia',    'Seguimiento angina estable',          'Pendiente',   'Control');
INSERT INTO CONSULTA (id_cita, id_medico, id_paciente, diagnostico, tratamiento, notas, fecha_hora) VALUES
(1, 2,  1, 'HipertensiÃ³n arterial estadio 1',
   'LosartÃ¡n 50 mg c/24 h, restricciÃ³n de sodio, ejercicio aerÃ³bico 30 min/dÃ­a',
   'PA 148/92 mmHg. Refiere cefalea matutina. Sin edema en miembros.',
   '2026-04-08 09:15:00'),
(2, 2,  2, 'Diabetes mellitus tipo 2 descontrolada',
   'Metformina 850 mg c/12 h con alimentos. Dieta hipoglucÃ­dica. Control en 2 meses.',
   'HbA1c 8.4%. Glucosa basal 168 mg/dL. Pies sin lesiones. Retina pendiente.',
   '2026-04-10 10:45:00'),
(3, 3,  3, 'Angina de pecho estable',
   'Atenolol 50 mg/dÃ­a, nitroglicerina sublingual SOS, evitar esfuerzo brusco.',
   'EKG en reposo sin alteraciones. Prueba de esfuerzo solicitada. Sin soplos.',
   '2026-04-15 11:20:00'),
(4, 8,  4, 'Crisis asmÃ¡tica leve',
   'Salbutamol 2 puff c/6 h por 5 dÃ­as. Budesonida inhalada mantenimiento.',
   'SpO2 97% al ingreso. FR 22/min. Sibilancias bilaterales. Buena respuesta al broncodilatador.',
   '2026-04-20 08:30:00'),
(5, 2,  5, 'Artritis reumatoide con actividad moderada',
   'Metotrexato 15 mg/semana, Ã¡cido fÃ³lico 5 mg/semana, naproxeno 500 mg c/12 h.',
   'DAS28 = 4.1. Rigidez matutina 45 min. TumefacciÃ³n MCF II-III bilateral.',
   '2026-05-05 09:45:00'),
(6, 3,  1, 'EvaluaciÃ³n cardiolÃ³gica preventiva â€” sin cardiopatÃ­a estructural',
   'Continuar LosartÃ¡n. Ecocardiograma en 12 meses. Ejercicio moderado.',
   'ECG normal. Sin soplos. FEVI 65%. PA 132/82 mmHg.',
   '2026-05-12 10:20:00'),
(7, 2,  6, 'Gastritis crÃ³nica antral por H. pylori probable',
   'Omeprazol 20 mg/dÃ­a + amoxicilina 1 g c/12 h + claritromicina 500 mg c/12 h Ã— 14 dÃ­as.',
   'Epigastralgia 6/10 posprandial. Sin hematemesis. Panendoscopia pendiente.',
   '2026-05-18 11:15:00'),
(8, 8,  7, 'Paciente sana â€” revisiÃ³n general sin hallazgos patolÃ³gicos',
   'Vacuna Td aplicada. Vitamina D 1000 UI/dÃ­a. PrÃ³ximo control en 1 aÃ±o.',
   'Peso 58 kg, Talla 165 cm, IMC 21.3. PA 110/70. ExploraciÃ³n fÃ­sica normal.',
   '2026-05-22 09:15:00'),
(9, 11, 8, 'Cefalea tensional crÃ³nica con componente migraÃ±oso probable',
   'Amitriptilina 25 mg/noche. Ibuprofeno 400 mg en crisis. Manejo de estrÃ©s.',
   'Cefalea bilateral opresiva, 3-4 episodios/semana. Sin focalidad neurolÃ³gica. TAC solicitada.',
   '2026-05-25 10:20:00');
INSERT INTO SIGNOS_VITALES (id_paciente, id_empleado, fecha_hora,
  tension_arterial, temperatura, frecuencia_cardiaca, frecuencia_respiratoria,
  peso_kg, talla_cm, saturacion_oxigeno) VALUES
(1, 4,  '2026-04-08 09:05:00', '148/92', 36.5,  82,  18, 68.0, 162.0, 98.0),
(2, 4,  '2026-04-10 10:35:00', '122/78', 36.7,  76,  16, 82.5, 170.0, 99.0),
(3, 9,  '2026-04-15 11:05:00', '128/82', 36.4,  88,  18, 61.0, 165.0, 97.0),
(4, 4,  '2026-04-20 08:10:00', '108/70', 37.1, 102,  22, 42.0, 148.0, 97.0),
(5, 4,  '2026-05-05 09:35:00', '118/74', 36.6,  78,  17, 63.5, 158.0, 98.0),
(1, 9,  '2026-05-12 10:05:00', '132/82', 36.3,  74,  16, 67.8, 162.0, 99.0),
(6, 4,  '2026-05-18 11:05:00', '118/76', 36.8,  80,  17, 75.0, 175.0, 99.0),
(7, 15, '2026-05-22 09:05:00', '110/68', 36.5,  72,  15, 58.0, 165.0, 99.0),
(8, 4,  '2026-05-25 10:05:00', '145/90', 36.9,  86,  18, 79.0, 172.0, 97.0);
INSERT INTO ORDEN_ESTUDIO (id_consulta, id_medico, id_paciente,
  tipo_estudio, nombre_estudio, fecha_solicitud, estatus) VALUES
(1, 2,  1, 'Laboratorio', 'BiometrÃ­a hemÃ¡tica completa',           '2026-04-08 09:20:00', 'Resultado disponible'),
(2, 2,  2, 'Laboratorio', 'Glucosa en ayuno y HbA1c',              '2026-04-10 10:50:00', 'Resultado disponible'),
(3, 3,  3, 'CardiologÃ­a', 'Prueba de esfuerzo con telemetrÃ­a',     '2026-04-15 11:30:00', 'Resultado disponible'),
(4, 8,  4, 'Imagen',      'RadiografÃ­a de tÃ³rax PA y lateral',     '2026-04-20 08:35:00', 'Resultado disponible'),
(5, 2,  5, 'Laboratorio', 'Factor reumatoide y PCR ultrasensible', '2026-05-05 09:50:00', 'Resultado disponible'),
(7, 2,  6, 'Endoscopia',  'Panendoscopia oral con biopsia antral', '2026-05-18 11:25:00', 'Pendiente'),
(9, 11, 8, 'Imagen',      'TAC de crÃ¡neo sin contraste',           '2026-05-25 10:30:00', 'Resultado disponible');
INSERT INTO RESULTADO_ESTUDIO (id_orden, id_tecnico, resultado,
  ruta_archivo, fecha_captura, estatus, requiere_impresion) VALUES
(1, 7,  'Hb 13.2 g/dL, leucocitos 7 200/Î¼L, plaquetas 210 000/Î¼L. Sin alteraciones relevantes.',
   '/resultados/2026/04/LAB-001.pdf', '2026-04-08 14:00:00', 'Entregado', 1),
(2, 7,  'Glucosa en ayuno 162 mg/dL. HbA1c 8.4%. Colesterol total 195 mg/dL.',
   '/resultados/2026/04/LAB-002.pdf', '2026-04-10 16:30:00', 'Entregado', 1),
(3, 13, 'Prueba de esfuerzo negativa para isquemia inducible. Capacidad funcional 8 METs.',
   '/resultados/2026/04/CARD-001.pdf','2026-04-22 10:00:00', 'Entregado', 1),
(4, 7,  'Campos pulmonares con hiperinsuflaciÃ³n leve. Sin consolidaciones ni derrame.',
   '/resultados/2026/04/IMG-001.pdf', '2026-04-20 10:00:00', 'Entregado', 0),
(5, 7,  'Factor reumatoide 64 UI/mL (VR < 14). PCR-us 18.4 mg/L. Actividad inflamatoria.',
   '/resultados/2026/05/LAB-003.pdf', '2026-05-06 15:00:00', 'Entregado', 1),
(7, 13, 'TAC sin lesiones ocupativas. Sin hemorragia. Engrosamiento mucoso sinusal leve.',
   '/resultados/2026/05/IMG-002.pdf', '2026-05-26 09:00:00', 'Entregado', 1);
INSERT INTO REFERENCIA_ESPECIALISTA (id_consulta, id_medico_remite, id_paciente,
  especialidad_destino, motivo_referencia, notas_clinicas, fecha_emision, estatus) VALUES
(3, 3,  3, 'CardiologÃ­a Intervencionista',
   'Angina estable con factores de riesgo mÃºltiples; requiere estratificaciÃ³n avanzada.',
   'Femenina 33 a. EKG basal normal. Prueba de esfuerzo negativa. SÃ­ntomas persisten.',
   '2026-04-15 11:45:00', 'Completada'),
(5, 2,  5, 'ReumatologÃ­a',
   'Artritis reumatoide con actividad moderada. Inicio de FARME justificado.',
   'DAS28 4.1. FR positivo. PCR elevada. Requiere seguimiento especializado.',
   '2026-05-05 10:05:00', 'Pendiente'),
(9, 11, 8, 'NefrologÃ­a',
   'IRC estadio 3 con HTA de difÃ­cil control y cefalea crÃ³nica.',
   'Creatinina 2.1 mg/dL. TFGe 38 mL/min. HTA con LosartÃ¡n 100 mg sin control Ã³ptimo.',
   '2026-05-25 10:40:00', 'Pendiente');
INSERT INTO MEDICAMENTO (nombre_sustancia, nombre_comercial, presentacion, tipo_medicamento, stock_minimo, dias_dispensacion) VALUES
('LosartÃ¡n potÃ¡sico',  'Cozaar',     'Tableta 50 mg',             'Antihipertensivo',   100, 'Lunes a viernes'),
('Metformina HCl',     'Glucophage', 'Tableta 850 mg',            'AntidiabÃ©tico',      150, 'Lunes a sÃ¡bado'),
('Atenolol',           'Tenormin',   'Tableta 50 mg',             'Beta-bloqueador',     80, 'Lunes a viernes'),
('Salbutamol',         'Ventolin',   'Inhalador 100 Î¼g/dosis',    'Broncodilatador',     50, 'Lunes a domingo'),
('Nitroglicerina',     'Nitrostat',  'Tableta sublingual 0.5 mg', 'Antianginal',         40, 'Lunes a viernes'),
('Paracetamol',        'Tempra',     'Tableta 500 mg',            'AnalgÃ©sico',         200, 'Lunes a domingo'),
('Omeprazol',          'Losec',      'CÃ¡psula 20 mg',             'Inhibidor de bomba', 120, 'Lunes a sÃ¡bado'),
('Amoxicilina',        'Amoxil',     'CÃ¡psula 500 mg',            'AntibiÃ³tico',        100, 'Lunes a sÃ¡bado'),
('Budesonida',         'Pulmicort',  'Inhalador 200 Î¼g/dosis',    'Corticosteroide',     30, 'Lunes a domingo'),
('Metotrexato',        'Methofar',   'Tableta 2.5 mg',            'FARME',               40, 'Lunes a viernes'),
('Naproxeno sÃ³dico',   'Naprosyn',   'Tableta 500 mg',            'AINE',                80, 'Lunes a sÃ¡bado'),
('Amitriptilina',      'Tryptanol',  'Tableta 25 mg',             'Antidepresivo',       50, 'Lunes a viernes');
INSERT INTO LOTE_MEDICAMENTO (id_medicamento, numero_lote, existencia_actual,
  fecha_caducidad, fecha_recepcion, precio_propuesto, precio_vigente,
  estatus_precio, id_farmaceutico, id_administrador_valida) VALUES
(1,  'LOT-LOS-2025-A', 320, '2027-06-30', '2025-11-01',  8.50,  8.50, 'Vigente', 5, 1),
(2,  'LOT-MET-2025-A', 480, '2027-08-31', '2025-10-15',  6.00,  6.00, 'Vigente', 5, 1),
(3,  'LOT-ATE-2025-A', 210, '2027-04-30', '2025-09-20',  5.50,  5.50, 'Vigente', 5, 1),
(4,  'LOT-SAL-2025-A',  95, '2026-12-31', '2025-12-01', 45.00, 45.00, 'Vigente', 5, 1),
(5,  'LOT-NIT-2025-A',  60, '2027-02-28', '2025-08-10', 22.00, 22.00, 'Vigente', 5, 1),
(6,  'LOT-PAR-2025-A', 600, '2028-01-31', '2025-07-05',  2.50,  2.50, 'Vigente', 5, 1),
(7,  'LOT-OME-2025-A', 400, '2027-10-31', '2025-10-01',  7.00,  7.00, 'Vigente', 5, 1),
(8,  'LOT-AMO-2025-A', 350, '2027-05-31', '2025-09-15',  4.50,  4.50, 'Vigente', 5, 1),
(9,  'LOT-BUD-2025-A',  80, '2027-03-31', '2025-11-20', 55.00, 55.00, 'Vigente', 5, 1),
(10, 'LOT-MTX-2025-A', 120, '2027-09-30', '2025-08-30', 18.00, 18.00, 'Vigente', 5, 1),
(11, 'LOT-NAP-2025-A', 280, '2027-07-31', '2025-10-10',  6.50,  6.50, 'Vigente', 5, 1),
(12, 'LOT-AMI-2025-A', 150, '2027-11-30', '2025-11-05', 12.00, 12.00, 'Vigente', 5, 1);
INSERT INTO RECETA (id_consulta, id_medico, id_paciente, tipo_receta, fecha_emision) VALUES
(1, 2,  1, 'Normal',   '2026-04-08 09:25:00'),
(2, 2,  2, 'Normal',   '2026-04-10 11:00:00'),
(3, 3,  3, 'Especial', '2026-04-15 11:40:00'),
(4, 8,  4, 'Normal',   '2026-04-20 08:45:00'),
(5, 2,  5, 'Especial', '2026-05-05 10:00:00'),
(7, 2,  6, 'Normal',   '2026-05-18 11:30:00'),
(9, 11, 8, 'Normal',   '2026-05-25 10:35:00');
INSERT INTO RECETA_DETALLE (id_receta, id_medicamento, dosis, indicaciones, cantidad) VALUES
(1, 1,  '50 mg',   'Una tableta cada 24 h en ayuno',                         30),
(1, 6,  '500 mg',  'Una tableta cada 8 h en caso de cefalea, mÃ¡x 3/dÃ­a',     15),
(2, 2,  '850 mg',  'Una tableta con desayuno y una con cena',                60),
(3, 3,  '50 mg',   'Una tableta cada 24 h por la maÃ±ana',                    30),
(3, 5,  '0.5 mg',  'Una tableta sublingual en crisis, mÃ¡x 3/dÃ­a',            10),
(4, 4,  '100 Î¼g',  'Dos puff cada 6 h por 5 dÃ­as',                            1),
(4, 9,  '200 Î¼g',  'Un puff cada 12 h como mantenimiento',                    1),
(5, 10, '15 mg',   'Seis tabletas de 2.5 mg una vez por semana (lunes)',      24),
(5, 11, '500 mg',  'Una tableta cada 12 h con alimentos',                    56),
(6, 7,  '20 mg',   'Una cÃ¡psula en ayuno 30 min antes del desayuno',         14),
(6, 8,  '500 mg',  'Una cÃ¡psula cada 12 h con alimentos durante 14 dÃ­as',    28),
(7, 12, '25 mg',   'Una tableta por la noche al dormir',                     30),
(7, 6,  '500 mg',  'Una tableta cada 8 h en crisis de cefalea',              20);
INSERT INTO DESPACHO_FARMACIA (id_receta, id_lote, id_farmaceutico, cantidad, precio_unitario, fecha_despacho) VALUES
(1,  1,  5, 30,   8.50, '2026-04-08 10:00:00'),
(1,  6,  5, 15,   2.50, '2026-04-08 10:02:00'),
(2,  2,  5, 60,   6.00, '2026-04-10 11:30:00'),
(3,  3,  5, 30,   5.50, '2026-04-15 12:00:00'),
(3,  5,  5, 10,  22.00, '2026-04-15 12:05:00'),
(4,  4,  5,  1,  45.00, '2026-04-20 09:00:00'),
(4,  9,  5,  1,  55.00, '2026-04-20 09:05:00'),
(5,  10, 5, 24,  18.00, '2026-05-05 10:30:00'),
(5,  11, 5, 56,   6.50, '2026-05-05 10:35:00'),
(6,  7,  5, 14,   7.00, '2026-05-18 12:00:00'),
(6,  8,  5, 28,   4.50, '2026-05-18 12:05:00'),
(7,  12, 5, 30,  12.00, '2026-05-25 11:00:00'),
(7,  6,  5, 20,   2.50, '2026-05-25 11:05:00');
INSERT INTO CAMA (numero_cama, tipo, area, estatus, id_consultorio) VALUES
(1, 'Individual', 'HospitalizaciÃ³n Gral', 'Disponible',  1),
(2, 'Individual', 'HospitalizaciÃ³n Gral', 'Disponible',  1),
(3, 'UCI',        'Cuidados Intensivos',  'Disponible',  4),
(4, 'Individual', 'PediatrÃ­a',            'Disponible',  3),
(5, 'Individual', 'HospitalizaciÃ³n Gral', 'En limpieza', 2),
(6, 'Doble',      'HospitalizaciÃ³n Gral', 'Disponible',  1),
(7, 'Individual', 'CardiologÃ­a',          'Ocupada',     2);
INSERT INTO SECUENCIA_CAMA (contador_global, fecha_actualizacion) VALUES
(7, '2026-05-25');
INSERT INTO HOSPITALIZACION (id_paciente, id_medico, id_cama,
  fecha_ingreso, fecha_egreso, costo_diario, numero_asignacion, estatus_limpieza_cama) VALUES
(4, 8, 4, '2026-04-20 09:00:00', '2026-04-22 12:00:00', 1800.00, 1, 'Limpia'),
(1, 2, 1, '2026-03-10 10:00:00', '2026-03-14 12:00:00', 1500.00, 2, 'Limpia'),
(8, 11,7, '2026-05-25 11:00:00', NULL,                  2000.00, 3, 'Sucia');
INSERT INTO NOTA_ENFERMERIA (id_paciente, id_empleado, id_hospitalizacion,
  tipo_cuidado, descripcion, fecha_hora) VALUES
(4, 4,  1, 'AdministraciÃ³n de medicamento',
   'Salbutamol 2 puff aplicado. Paciente tolera bien. SpO2 sube a 99%.', '2026-04-20 10:00:00'),
(4, 4,  1, 'Control de signos vitales',
   'FC 88, FR 18, Temp 36.8Â°C, PA 108/68. Paciente estable y tranquilo.','2026-04-20 14:00:00'),
(1, 9,  2, 'AdministraciÃ³n de medicamento',
   'LosartÃ¡n 50 mg vÃ­a oral. Sin reacciones adversas. Paciente cooperador.','2026-03-10 21:00:00'),
(1, 15, 2, 'Cambio de posiciÃ³n',
   'Cambio postural c/2 h para prevenir Ãºlceras por presiÃ³n. Piel Ã­ntegra.','2026-03-11 02:00:00'),
(8, 4,  3, 'Control de signos vitales',
   'PA 148/94, FC 88, SpO2 97%, Temp 37.0Â°C. Pendiente ajuste antihipertensivo.','2026-05-25 18:00:00'),
(8, 15, 3, 'AdministraciÃ³n de medicamento',
   'LosartÃ¡n 100 mg administrado. Paciente refiere leve mejorÃ­a de cefalea.','2026-05-25 22:00:00');
INSERT INTO INSUMO (nombre_articulo, categoria, stock_actual, stock_minimo, area_asignada) VALUES
('Guantes de nitrilo talla M',         'Consumible', 450, 100, 'HospitalizaciÃ³n'),
('Jeringas 5 mL',                      'Consumible', 290,  80, 'EnfermerÃ­a'),
('ElectrocardiÃ³grafo 12 derivaciones', 'Equipo',       2,   1, 'CardiologÃ­a'),
('OxÃ­metro de pulso portÃ¡til',         'Equipo',       7,   2, 'Urgencias'),
('Gasas estÃ©riles 10x10 cm',           'Consumible', 780, 200, 'QuirÃ³fano'),
('CatÃ©ter venoso perifÃ©rico 20G',      'Consumible', 120,  40, 'Urgencias'),
('TensiÃ³metro digital de pared',       'Equipo',       5,   2, 'HospitalizaciÃ³n'),
('Bolsas para suero 500 mL',           'Consumible', 200,  60, 'Urgencias');
INSERT INTO EQUIPO_MEDICO (id_insumo, estatus_equipo, fecha_ultimo_mantenimiento, proxima_revision) VALUES
(3, 'Operativo', '2026-01-15', '2026-07-15'),
(4, 'Operativo', '2026-03-01', '2026-09-01'),
(7, 'Operativo', '2026-02-10', '2026-08-10');
INSERT INTO MOVIMIENTO_INSUMO (id_insumo, id_empleado, tipo_movimiento, cantidad, motivo, fecha_hora) VALUES
(1, 4,  'Salida',   50, 'Uso hospitalizaciÃ³n paciente 0000000004', '2026-04-20 09:30:00'),
(2, 9,  'Salida',   10, 'Toma de muestras en urgencias',           '2026-04-20 08:00:00'),
(1, 4,  'Entrada', 200, 'ReposiciÃ³n de stock mensual',             '2026-04-01 08:00:00'),
(6, 9,  'Salida',    8, 'CanalizaciÃ³n de pacientes en urgencias',  '2026-05-25 11:30:00'),
(8, 15, 'Salida',   12, 'SoluciÃ³n IV paciente 0000000008',         '2026-05-25 12:00:00'),
(5, 4,  'Salida',   20, 'CuraciÃ³n herida paciente 0000000001',     '2026-03-10 14:00:00');
INSERT INTO ALERTA_STOCK (id_insumo, tipo_origen, area, stock_al_momento, fecha_generacion, estatus) VALUES
(4, 'Stock mÃ­nimo', 'Urgencias',  7, '2026-05-24 07:00:00', 'Activa'),
(2, 'Stock mÃ­nimo', 'EnfermerÃ­a', 85,'2026-05-20 07:00:00', 'Resuelta');
INSERT INTO PAGO (id_paciente, id_cajero, folio_pago, monto_total, metodo_pago, fecha_hora, estatus_pago) VALUES
(1, 6,  'FOLIO-000001',  7850.00, 'Efectivo',      '2026-03-14 13:00:00', 'Pagado'),
(4, 6,  'FOLIO-000002',  5290.00, 'Tarjeta',       '2026-04-22 13:30:00', 'Pagado'),
(2, 6,  'FOLIO-000003',   710.00, 'Transferencia', '2026-04-10 12:00:00', 'Pagado'),
(3, 14, 'FOLIO-000004',   580.00, 'Efectivo',      '2026-04-15 13:00:00', 'Pagado'),
(5, 14, 'FOLIO-000005',  1228.00, 'Tarjeta',       '2026-05-05 11:00:00', 'Pagado'),
(6, 6,  'FOLIO-000006',   388.50, 'Efectivo',      '2026-05-18 12:30:00', 'Pagado'),
(8, 6,  'FOLIO-000007',  4350.00, 'Seguro IMSS',   '2026-05-25 11:30:00', 'Pendiente');
INSERT INTO PAGO_DETALLE (id_pago, tipo_concepto,
  id_referencia_consulta, id_referencia_estudio, id_referencia_despacho,
  id_referencia_hospitalizacion, monto, descripcion) VALUES
(1, 'HospitalizaciÃ³n', NULL, NULL, NULL, 2,    6000.00, '4 dÃ­as cama individual (Mar 10â€“14)'),
(1, 'Consulta',        1,    NULL, NULL, NULL,  350.00, 'Consulta medicina general'),
(1, 'Despacho',        NULL, NULL, 1,    NULL,  255.00, 'LosartÃ¡n 30 tab + Paracetamol 15 tab'),
(1, 'Estudio',         NULL, 1,    NULL, NULL, 1245.00, 'BiometrÃ­a hemÃ¡tica completa'),
(2, 'HospitalizaciÃ³n', NULL, NULL, NULL, 1,    3600.00, '2 dÃ­as cama pediatrÃ­a (Abr 20â€“22)'),
(2, 'Consulta',        4,    NULL, NULL, NULL,  350.00, 'Consulta pediatrÃ­a urgencia'),
(2, 'Despacho',        NULL, NULL, 6,    NULL,  245.00, 'Salbutamol inhalador'),
(2, 'Despacho',        NULL, NULL, 7,    NULL,  335.00, 'Budesonida inhalador'),
(2, 'Estudio',         NULL, 4,    NULL, NULL,  760.00, 'RadiografÃ­a de tÃ³rax'),
(3, 'Consulta',        2,    NULL, NULL, NULL,  350.00, 'Consulta medicina general'),
(3, 'Despacho',        NULL, NULL, 3,    NULL,  360.00, 'Metformina 60 tab'),
(4, 'Consulta',        3,    NULL, NULL, NULL,  350.00, 'Consulta cardiologÃ­a'),
(4, 'Despacho',        NULL, NULL, 4,    NULL,  230.00, 'Atenolol + Nitroglicerina'),
(5, 'Consulta',        5,    NULL, NULL, NULL,  350.00, 'Consulta medicina general'),
(5, 'Despacho',        NULL, NULL, 8,    NULL,  432.00, 'Metotrexato + Naproxeno'),
(5, 'Estudio',         NULL, 5,    NULL, NULL,  446.00, 'Factor reumatoide y PCR-us'),
(6, 'Consulta',        7,    NULL, NULL, NULL,  350.00, 'Consulta medicina general'),
(6, 'Despacho',        NULL, NULL, 10,   NULL,   98.00, 'Omeprazol 14 cÃ¡ps'),
(6, 'Despacho',        NULL, NULL, 11,   NULL,  126.00, 'Amoxicilina 28 cÃ¡ps'),
(7, 'HospitalizaciÃ³n', NULL, NULL, NULL, 3,    2000.00, '1 dÃ­a cama cardiologÃ­a (May 25)'),
(7, 'Consulta',        9,    NULL, NULL, NULL,  350.00, 'Consulta neurologÃ­a'),
(7, 'Estudio',         NULL, 7,    NULL, NULL, 1650.00, 'TAC de crÃ¡neo sin contraste'),
(7, 'Despacho',        NULL, NULL, 12,   NULL,  350.00, 'Amitriptilina 30 tab');
INSERT INTO MODIFICACION_PAGO (id_pago, id_solicitante, id_administrador,
  motivo, fecha_solicitud, fecha_autorizacion, estatus) VALUES
(7, 6, 1, 'Error en concepto hospitalizaciÃ³n â€” se capturÃ³ tarifa incorrecta',
   '2026-05-26 09:00:00', '2026-05-26 11:00:00', 'Autorizada');
INSERT INTO MANTENIMIENTO (id_administrador, fecha_hora_inicio, duracion_minutos, descripcion, estatus) VALUES
(1, '2026-06-15 22:00:00', 240, 'ActualizaciÃ³n del sistema y respaldo general de base de datos', 'Programado'),
(1, '2026-04-20 01:00:00', 120, 'Mantenimiento servidor de archivos de estudios y resultados',  'Completado'),
(1, '2026-03-01 02:00:00',  60, 'Parche de seguridad mÃ³dulo de autenticaciÃ³n',                  'Completado');
INSERT INTO ALERTA_MANTENIMIENTO (id_mantenimiento, area_notificada, fecha_envio, estatus_envio) VALUES
(1, 'MÃ©dicos',     '2026-06-14 08:00:00', 'Enviado'),
(1, 'EnfermerÃ­a',  '2026-06-14 08:00:00', 'Enviado'),
(1, 'Farmacia',    '2026-06-14 08:00:00', 'Enviado'),
(2, 'Laboratorio', '2026-04-19 08:00:00', 'Enviado'),
(3, 'Todos',       '2026-02-28 17:00:00', 'Enviado');
INSERT INTO LOG_AUDITORIA (id_usuario, modulo, accion, fecha_hora, ip_equipo, detalle_cambio) VALUES
(1,  'Empleados', 'Alta de empleado',            '2026-01-10 08:30:00', '192.168.1.10', 'Alta de 0000000015 (AndrÃ©s PeÃ±a DomÃ­nguez)'),
(2,  'Consulta',  'Nueva consulta generada',     '2026-04-08 09:15:00', '192.168.1.21', 'Consulta paciente 0000000001 â€” HipertensiÃ³n estadio 1'),
(5,  'Farmacia',  'Despacho de medicamento',     '2026-04-08 10:02:00', '192.168.1.30', 'Despacho LosartÃ¡n 30 tab, Lote LOT-LOS-2025-A'),
(2,  'Consulta',  'Nueva consulta generada',     '2026-04-10 10:45:00', '192.168.1.21', 'Consulta paciente 0000000002 â€” Diabetes descontrolada'),
(3,  'Consulta',  'Nueva consulta generada',     '2026-04-15 11:20:00', '192.168.1.22', 'Consulta paciente 0000000003 â€” Angina estable'),
(6,  'Caja',      'Pago registrado',             '2026-03-14 13:00:00', '192.168.1.40', 'Pago FOLIO-000001, monto $7 850.00, Efectivo'),
(1,  'Empleados', 'Baja programada de empleado', '2025-12-01 09:00:00', '192.168.1.10', 'Baja 0000000010 (Gabriela Soto) por renuncia voluntaria'),
(7,  'Cita',      'Consulta de prÃ³xima cita',    '2026-06-01 07:45:00', '192.168.2.55', 'Paciente 0000000003 consultÃ³ su cita pendiente'),
(11, 'Consulta',  'Nueva consulta generada',     '2026-05-25 10:20:00', '192.168.1.23', 'Consulta paciente 0000000008 â€” Cefalea crÃ³nica'),
(1,  'Sistema',   'Respaldo completado',         '2026-04-20 03:05:00', '192.168.1.10', 'Respaldo nocturno exitoso. TamaÃ±o: 2.4 GB');
INSERT INTO REASIGNACION_PACIENTE (id_empleado_saliente, id_empleado_receptor,
  id_paciente, fecha_reasignacion, id_administrador) VALUES
(10, 5, 2, '2025-12-31 12:00:00', 1);
