# MediCore HMS — Sistema de Gestión Hospitalario

> Proyecto de la materia **Análisis y Diseño de Sistemas**  
> Instituto Politécnico Nacional — Escuela Superior de Cómputo (ESCOM)  
> Metodología: RUP (Rational Unified Process) · Fases: Inicio y Elaboración

---

## Descripción general

**MediCore HMS** es un sistema web de gestión hospitalaria desarrollado como proyecto académico. Permite administrar de forma integral los procesos internos de un hospital: personal, citas médicas, expedientes clínicos, farmacia, laboratorio, pagos y mantenimiento del sistema.

El sistema está diseñado para **8 actores** con roles y permisos diferenciados:

| Actor | Portal | Descripción |
|---|---|---|
| Administrador | `/Admin_Login.html` | Control total del sistema, empleados, reportes y auditoría |
| Médico | `/Doctor_Login.html` | Gestión de citas, expedientes y hospitalizaciones |
| Enfermero | `/Nurse_Login.html` | Seguimiento de pacientes hospitalizados |
| Farmacéutico | `/Worker_Login.html` | Inventario de medicamentos y despacho |
| Técnico de Laboratorio | `/Worker_Login.html` | Registro de resultados de estudios |
| Cajero / Secretario | `/Secretary_Login.html` | Registro de pagos y facturación |
| Paciente | `/Patient_Login.html` | Consulta de citas y expediente personal |
| Sistema | — | Procesos automáticos (bajas, alertas, mantenimiento) |

---

## Módulo implementado

Este repositorio cubre el actor **Administrador** (CU-ADM-01 al CU-ADM-18), incluyendo:

- **Autenticación segura** con reCAPTCHA v2, bcrypt (costo 12) y control de sesión de 15 min
- **Gestión de empleados**: alta, consulta, modificación y baja con período de gracia de 30 días
- **Autorización de pagos**: revisión y cancelación de emergencia con clave
- **Validación de precios** de medicamentos propuestos por farmacéuticos
- **Programación de mantenimiento** con notificación automática a áreas críticas
- **Reportes**: financieros, clínicos, citas, farmacia, laboratorio e inventario
- **Bitácora de auditoría** completa (LOG_AUDITORIA) con registro de cada acción
- **Notificaciones** en tiempo real: stock crítico, medicamentos por caducar, bajas próximas

---

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript (Fetch API) |
| Backend | PHP 8.2 |
| Base de datos | MySQL 8 (InnoDB, transacciones, claves foráneas) |
| Servidor | Apache (XAMPP) |
| Seguridad | bcrypt · reCAPTCHA v2 · RBAC · sesiones PHP |
| Control de versiones | Git / GitHub |

---

## Requisitos previos

- [XAMPP](https://www.apachefriends.org/) con PHP 8.0+ y MySQL
- Navegador moderno (Chrome, Firefox, Edge)
- Conexión a internet (para cargar Bootstrap CDN y reCAPTCHA)

---

## Instalación local

```bash
# 1. Clonar el repositorio dentro de htdocs
git clone https://github.com/LACRE-hub/PROYECTO_ADS.git C:/xampp/htdocs/PROYECTO_ADS

# 2. Importar la base de datos en phpMyAdmin o MySQL CLI
mysql -u root -p < sistema_hospitalario_mysql.sql

# 3. Configurar las variables de entorno
cp .env.example .env
# Editar .env con tu clave secreta de reCAPTCHA
```

**4. Iniciar XAMPP** (Apache + MySQL) y acceder a:
```
http://localhost/PROYECTO_ADS/index.html
```

---

## Estructura del proyecto

```
PROYECTO_ADS/
├── index.html                  # Página de inicio / selección de portal
├── Admin_Login.html            # Login del Administrador (con reCAPTCHA)
├── panel_Administrador.html    # Panel principal del Administrador
├── panel_Patient.html          # Panel del Paciente
├── css/                        # Hojas de estilo por módulo
├── js/                         # Lógica del cliente por módulo
├── imagenes/                   # Assets gráficos
├── php/
│   ├── login.php               # Autenticación (todos los actores)
│   ├── logout.php              # Cierre de sesión con registro en bitácora
│   ├── db.php                  # Conexión PDO a MySQL
│   ├── config.php              # Carga de variables desde .env
│   ├── middleware/
│   │   └── auth_check.php      # Guards de sesión y RBAC
│   └── api/
│       ├── empleados.php       # CRUD de empleados + auto-baja RN-05
│       ├── pagos.php           # Autorización de modificaciones de pago
│       ├── precios.php         # Validación de precios de medicamentos
│       ├── mantenimiento.php   # Programación de ventanas de mantenimiento
│       ├── notificaciones.php  # Alertas en tiempo real para el Admin
│       ├── reportes.php        # Generación de reportes (RF-31 a RF-36)
│       ├── auditoria.php       # Consulta de bitácora (RF-24)
│       ├── dashboard.php       # Métricas del panel principal
│       └── session_ping.php    # Verificación de sesión activa (RNF-07)
├── sistema_hospitalario_mysql.sql   # Script completo de la base de datos
├── .env.example                # Plantilla de variables de entorno
└── .gitignore
```

---

## Seguridad implementada

| Requisito | Implementación |
|---|---|
| **RN-01 / RT-05** | Verificación de reCAPTCHA v2 en servidor (no solo cliente) |
| **RNF-02** | Contraseñas hasheadas con bcrypt costo 12 |
| **RNF-07 / RT-06** | Sesión expira a los 15 min de inactividad; advertencia a los 13 min |
| **RN-02 / RNF-04** | Control de acceso por rol (RBAC) en cada endpoint |
| **RT-08** | Bitácora de auditoría en LOG_AUDITORIA para toda acción relevante |
| **RT-11** | Consultas parametrizadas PDO (sin SQL injection) |
| **RT-12** | Transacciones COMMIT/ROLLBACK en operaciones críticas |
| **RN-05** | Auto-desactivación de empleados al vencer el período de gracia |

---

## Normatividad de referencia

- **NOM-004-SSA3** — Expediente clínico (integridad y conservación de datos)
- **NOM-024-SSA3** — Sistemas de información en salud
- **LFPDPPP** — Ley Federal de Protección de Datos Personales
- **ISO/IEC 27001** — Seguridad de la información

---

## Configuración de variables de entorno

Copia `.env.example` como `.env` y completa los valores:

```env
DB_HOST=localhost
DB_NAME=sistema_hospitalario
DB_USER=root
DB_PASS=

# Obtén tu clave en: https://www.google.com/recaptcha/admin
RECAPTCHA_SECRET=tu_clave_secreta_aqui
```

> ⚠️ El archivo `.env` está en `.gitignore` y **nunca debe subirse al repositorio**.

---

## Equipo de desarrollo

Proyecto académico desarrollado por el equipo **LACRE** para la materia Análisis y Diseño de Sistemas — IPN ESCOM, 2026.

Actor implementado en este repositorio: **Administrador**.
