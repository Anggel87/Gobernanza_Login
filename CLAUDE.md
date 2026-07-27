# CLAUDE.md — CheckMate: Contexto Técnico Completo

> Este documento es la fuente de verdad técnica del proyecto CheckMate para Claude Code.
> Contiene el esquema completo de la base de datos, modelos Eloquent, relaciones, endpoints por rol,
> catálogo de errores, estandarización de respuestas y reglas de negocio críticas.
> **Consulta este archivo antes de generar cualquier migración, modelo, controlador, request o policy.**

---

## 1. VISIÓN GENERAL DEL PROYECTO

CheckMate es un sistema de control de asistencia NFC para instituciones de educación superior en México (Universidad Tecnológica de Torreón). Los alumnos registran su asistencia acercando su tarjeta NFC a un dispositivo ESP32 instalado en el aula. El sistema notifica a tutores familiares, gestiona incidentes de emergencia y ofrece reportes por rol.

**Stack:**
- Backend: Laravel 13 · PHP 8.4 · MySQL 8.4 LTS
- Auth: Laravel Sanctum 4.x (tokens Bearer)
- Zona horaria: `America/Matamoros`
- Base URL de la API: `/api/v1`
- Content-Type: `application/json`

---

## 2. ARQUITECTURA RBAC

El sistema usa un modelo RBAC puro. No existen tablas separadas de `students`, `teachers` ni `directors`. **Todo actor del sistema es un registro en `users`**, diferenciado por `role_id`.

### Roles del sistema (tabla `roles`)
| role_id | Nombre en código | Descripción |
|---------|-----------------|-------------|
| 1 | `alumno` | Estudiante inscrito en un grupo |
| 2 | `profesor` | Docente asignado a horarios |
| 3 | `tutor_academico` | Profesor que además es tutor de grupo (hereda `profesor`) |
| 4 | `administrator` | Administrador Escolar — CRUD completo (acceso por sub-rol de módulo) |
| 5 | `career_director` | Director de Carrera — consulta e inteligencia de su carrera |

> **IMPORTANTE:** El rol "Director de Carrera" en BD se llama `career_director` (role_id = 5) y es el director asignado a cada carrera en `careers.director_id`.
> El "Administrador Escolar" se llama `administrator` (role_id = 4). Su acceso a los diferentes CRUDs se controla mediante sub-roles (ver sección de comentarios al final).
> `tutor_academico` hereda todos los permisos de `profesor` más los suyos propios.

---

## 3. ESQUEMA DE BASE DE DATOS

### Convenciones generales
- Soft deletes: campo `is_active` (TINYINT, default 1). **Nunca usar `deleted_at` en este proyecto.**
- Timestamps: `created_at` / `updated_at` de Laravel en todas las tablas de negocio.
- IDs: UNSIGNED donde lo indica el esquema. Respetar los tipos exactos (TINYINT, SMALLINT, MEDIUMINT, INT).
- FKs que apuntan a `users.id` representan actores diferenciados por `role_id`.

---

### 3.1 Módulo de Seguridad y Permisos

#### `roles`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | TINYINT UNSIGNED | PK, AI, UQ | Identificador único del rol |
| name | VARCHAR(45) | NN | Nombre del rol (ej. Alumno, Profesor, Director) |

#### `permissions`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | TINYINT UNSIGNED | PK, UQ | ID del permiso |
| name | VARCHAR(100) | NN | Nombre del permiso |
| key_name | VARCHAR(100) | NN, UQ | Nombre técnico (ej. `attendance.create`) |
| description | VARCHAR(255) | — | Descripción |
| is_active | TINYINT | default 1 | Si está activo |

#### `permission_groups`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | TINYINT UNSIGNED | PK, AI, UQ | ID del grupo |
| name | VARCHAR(50) | NN | Nombre |
| key_name | VARCHAR(50) | NN, UQ | Nombre técnico |
| description | VARCHAR(255) | — | Descripción |
| is_active | TINYINT | NN, default 1 | Si está activo |

#### `permission_group_permission` (pivote N:M)
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| permissions_id | TINYINT UNSIGNED | PK, NN, FK→permissions.id | FK permiso |
| permission_groups_id | TINYINT UNSIGNED | PK, NN, FK→permission_groups.id | FK grupo |

#### `user_permission_overrides`
Permite otorgar (ALLOW) o revocar (DENY) permisos individuales fuera del rol base.
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | SMALLINT UNSIGNED | PK, UQ | ID de la regla |
| users_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Usuario afectado |
| permissions_id | MEDIUMINT UNSIGNED | NN, FK→permissions.id | Permiso afectado |
| type | ENUM('ALLOW','DENY') | NN | Tipo de override |

---

### 3.2 Módulo de Usuarios y Perfiles

#### `users` ← TABLA CENTRAL DEL SISTEMA
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID del usuario |
| role_id | TINYINT UNSIGNED | FK→roles.id | Rol del usuario |
| first_name | VARCHAR(45) | NN | Primer nombre |
| second_name | VARCHAR(45) | — | Segundo nombre |
| first_surname | VARCHAR(45) | NN | Primer apellido |
| second_surname | VARCHAR(45) | NN | Segundo apellido |
| email | VARCHAR(155) | NN, UQ | Email único |
| password | VARCHAR(255) | NN | Hash bcrypt |
| verified_at | DATETIME | — | Fecha de verificación |
| active | TINYINT | NN, default 1 | Si está activo |
| photo | VARCHAR(255) | NN | URL de foto |
| phone | VARCHAR(10) | NN | Teléfono (10 dígitos) |
| birth_date | DATE | NN | Fecha de nacimiento |
| gender | ENUM('M','F','OTRO') | NN | Género |

**Regla de negocio:** Si `active = 0`, el usuario NO puede iniciar sesión (error AUTH03).
Si `verified_at IS NULL`, el usuario NO puede iniciar sesión (error AUTH04).

#### `user_details` (extensión 1:1 de users)
Centraliza datos de hardware NFC/QR. Relación estricta 1:1 con `users`.
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID del detalle |
| user_id | MEDIUMINT UNSIGNED | NN, UQ, FK→users.id | Relación 1:1 |
| nfc_uid | VARCHAR(100) | NN, UQ | Código NFC único |
| qr_uuid | VARCHAR(36) | NN, UQ | UUID del QR |

**Regex NFC:** `^[A-Fa-f0-9:\- ]{1,100}$`

#### `addresses`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID |
| street | VARCHAR(90) | NN | Calle |
| number | VARCHAR(31) | NN | Número |
| neighborhood | VARCHAR(70) | NN | Colonia |
| postal_code | VARCHAR(5) | NN | CP |
| city | VARCHAR(30) | NN | Ciudad |
| state | VARCHAR(16) | NN | Estado |
| country | VARCHAR(6) | NN | País |
| users_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Usuario propietario |
| tutors_id | MEDIUMINT UNSIGNED | nullable, FK→tutors.id | Tutor familiar (opcional) |

---

### 3.3 Módulo de Estructura Académica

#### `school_years`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | SMALLINT UNSIGNED | PK, AI, UQ | ID |
| name | VARCHAR(50) | NN, UQ | Nombre (ej. "2026-2027") |
| start_date | DATE | NN | Inicio del ciclo |
| end_date | DATE | NN | Fin del ciclo |
| status | ENUM('UPCOMING','ACTIVE','FINISHED') | NN, default 'UPCOMING' | Estado |

**Regex name:** `^\d{4}-\d{4}$`
**Regla:** Solo puede haber un ciclo con `status = 'ACTIVE'` a la vez. Al activar uno, el anterior pasa a `FINISHED`.

#### `careers`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | TINYINT UNSIGNED | PK, AI, UQ | ID |
| name | VARCHAR(150) | NN | Nombre completo |
| short_name | VARCHAR(20) | — | Abreviación (ej. "TICS") |
| code | VARCHAR(30) | NN, UQ | Código único |
| is_active | TINYINT | NN, default 1 | Si está activa |
| director_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Director de Carrera responsable |

**Regex code:** `^[A-Z0-9\-]{2,30}$`

#### `groups`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID |
| school_year_id | SMALLINT UNSIGNED | NN, FK→school_years.id | Ciclo escolar |
| career_id | TINYINT UNSIGNED | NN, FK→careers.id | Carrera |
| section | VARCHAR(5) | NN | Sección (ej. "A") |
| grade | VARCHAR(5) | NN | Grado (ej. "1") |
| shift | ENUM('MORNING','AFTERNOON','ENGINEERING') | — | Turno |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activo |

**UNIQUE compuesto:** `(school_year_id, career_id, grade, section)` — error GRP03 si se viola.

---

### 3.4 Módulo de Tutores Familiares

#### `tutors`
Personas externas al sistema (padres, madres, familiares). **NO tienen cuenta de usuario ni pueden iniciar sesión.**
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID |
| first_name | VARCHAR(45) | NN | Primer nombre |
| second_name | VARCHAR(45) | — | Segundo nombre |
| first_surname | VARCHAR(45) | NN | Primer apellido |
| second_surname | VARCHAR(45) | NN | Segundo apellido |
| phone | VARCHAR(10) | NN | Teléfono |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activo |

#### `student_tutor` (pivote N:M users ↔ tutors)
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID |
| tutor_id | MEDIUMINT UNSIGNED | NN, FK→tutors.id | Tutor familiar |
| student_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Alumno (role=alumno) |
| relationship | VARCHAR(50) | NN | Relación (ej. "Madre") |
| is_primary | TINYINT | NN, default 0 | Si es el tutor principal |
| receives_notifications | TINYINT UNSIGNED | NN, default 1 | Si recibe notificaciones |

**Regla de negocio crítica:** Todo alumno debe tener al menos un tutor asignado. No se puede eliminar el único tutor (error TUT02).

#### `notification_preferences`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | INT UNSIGNED | PK, AI, UQ | ID |
| tutor_id | MEDIUMINT UNSIGNED | NN, FK→tutors.id | Tutor |
| absences | TINYINT | NN, default 1 | Notif. inasistencia |
| lates | TINYINT | NN, default 1 | Notif. retardos |
| incidents | TINYINT | NN, default 1 | Notif. incidentes |
| justifications | TINYINT | NN, default 1 | Notif. justificantes |
| claims | TINYINT | NN, default 1 | Notif. reclamos |
| announcements | TINYINT | NN, default 1 | Notif. anuncios |

Se crea automáticamente con todos en `1` al registrar un alumno con su tutor.

---

### 3.5 Módulo de Tutor Académico

#### `academic_tutors`
Un profesor puede ser designado como tutor académico de uno o más grupos.
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | SMALLINT UNSIGNED | PK, AI, UQ | ID |
| user_id | MEDIUMINT UNSIGNED | NN, UQ, FK→users.id | Profesor (1:1 con users) |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activo |

#### `group_academic_tutor` (intermedia N:M con atributos)
Un tutor académico puede tener varios grupos; un grupo puede tener varios tutores académicos.
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| group_id | MEDIUMINT UNSIGNED | NN, FK→groups.id | Grupo |
| academic_tutor_id | SMALLINT UNSIGNED | NN, FK→academic_tutors.id | Tutor académico |
| is_active | TINYINT | NN, default 1 | Si la asignación está activa |
| assigned_at | DATE | NN | Fecha de asignación |

---

### 3.6 Módulo de Materias, Salones y Dispositivos

#### `subjects`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | SMALLINT UNSIGNED | PK, AI, UQ | ID |
| name | VARCHAR(100) | NN | Nombre |
| code | VARCHAR(30) | NN, UQ | Código único |
| description | VARCHAR(255) | — | Descripción |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activa |

**Regex code:** `^[A-Z0-9\-]{2,30}$`

#### `classroom`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | TINYINT UNSIGNED | PK, AI, UQ | ID |
| name | VARCHAR(45) | NN | Nombre (ej. "Aula 201") |
| building | VARCHAR(45) | NN | Edificio (ej. "B") |

#### `devices` (ESP32)
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | SMALLINT UNSIGNED | PK, AI, UQ | ID |
| mac_address | VARCHAR(20) | NN, UQ | MAC del ESP32 |
| ip | VARCHAR(30) | — | IP local |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activo |
| classroom_id | TINYINT UNSIGNED | NN, FK→classroom.id | Salón asignado |

**Regex mac_address:** `^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$`
**Regex ip:** `^(\d{1,3}\.){3}\d{1,3}$`

---

### 3.7 Módulo de Horarios y Configuración

#### `schedules`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | INT UNSIGNED | PK, AI, UQ | ID |
| school_year_id | SMALLINT UNSIGNED | NN, FK→school_years.id | Ciclo escolar |
| group_id | MEDIUMINT UNSIGNED | NN, FK→groups.id | Grupo |
| subject_id | SMALLINT UNSIGNED | NN, FK→subjects.id | Materia |
| teacher_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Profesor (role=profesor) |
| classroom_id | TINYINT UNSIGNED | NN, FK→classroom.id | Salón |
| day_of_week | ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') | NN | Día |
| start_time | TIME | NN | Hora inicio |
| end_time | TIME | NN | Hora fin |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activo |

**Regla:** No se puede desactivar un profesor con `schedules.is_active = 1` (error USR05).

#### `attendance_settings` (1:1 con schedules)
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | INT UNSIGNED | PK, AI, UQ | ID |
| schedule_id | INT UNSIGNED | NN, UQ, FK→schedules.id | Horario (1:1) |
| present_tolerance_minutes | TINYINT UNSIGNED | NN, default 10 | Minutos para asistencia a tiempo |
| late_tolerance_minutes | TINYINT UNSIGNED | NN, default 30 | Minutos para retardo |
| allow_manual_attendance | TINYINT UNSIGNED | NN, default 0 | Permite lista manual |
| is_active | TINYINT UNSIGNED | NN, default 1 | Si está activa |

---

### 3.8 Módulo de Asistencias

#### `attendances`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | INT UNSIGNED | PK, AI, UQ | ID |
| student_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Alumno (role=alumno) |
| schedule_id | INT UNSIGNED | NN, FK→schedules.id | Horario |
| devices_id | SMALLINT UNSIGNED | NN, FK→devices.id | Dispositivo que registró |
| registered_at | DATETIME | NN | Momento del registro |
| status | ENUM('PRESENT','LATE','ABSENT','JUSTIFIED') | NN | Estado |
| method | ENUM('NFC','QR','MANUAL','SYSTEM') | NN | Método de registro |

**Lógica de status:**
- `PRESENT`: llegó dentro de `present_tolerance_minutes`
- `LATE`: llegó después de la tolerancia pero dentro de `late_tolerance_minutes`
- `ABSENT`: no registró asistencia (el sistema lo marca al cerrar la sesión)
- `JUSTIFIED`: fue `ABSENT` pero tiene justificante aprobado

**Solo se pueden justificar registros con `status = ABSENT`** (error ATT04 si es otro estado).

#### `justifications`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID |
| attendance_id | INT UNSIGNED | NN, FK→attendances.id | Asistencia a justificar |
| justified_by_user_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Quien justificó |
| reason | VARCHAR(255) | NN | Motivo |
| file | VARCHAR(255) | — | URL del archivo evidencia |
| justified_at | DATETIME | NN | Fecha de justificación |
| status | ENUM('PENDING','ACCEPTED','REJECTED') | NN, default 'PENDING' | Estado |

**Regla:** Un `attendance_id` puede tener solo UN justificante (error JUST03 si duplicado).

#### `claims`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | MEDIUMINT UNSIGNED | PK, AI, UQ | ID |
| attendance_id | INT UNSIGNED | NN, FK→attendances.id | Asistencia reclamada |
| tutor_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Tutor que envía el reclamo |
| director_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Director que lo revisa |
| description | VARCHAR(255) | NN | Descripción |
| evidence | VARCHAR(255) | — | URL evidencia |
| status | ENUM('PENDING','ACCEPTED','REJECTED') | NN, default 'PENDING' | Estado |

---

### 3.9 Módulo de Incidentes

#### `incidents`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | SMALLINT UNSIGNED | PK, AI, UQ | ID |
| reported_by_user_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Quién lo reportó |
| schedule_id | INT UNSIGNED | NN, FK→schedules.id | Clase relacionada |
| title | VARCHAR(255) | — | Título |
| description | VARCHAR(255) | — | Descripción |
| severity | ENUM('LOW','MEDIUM','HIGH','CRITICAL') | — | Severidad |
| evidence | VARCHAR(255) | — | URL evidencia |
| incident_at | DATETIME | NN | Cuándo ocurrió |
| status | ENUM('ACTIVE','IN_REVIEW','RESOLVED','CANCELLED') | NN, default 'ACTIVE' | Estado |
| reviewed_by_user_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Responsable de revisión |
| type | VARCHAR(25) | NN | Tipo (FIRE, GAS, EARTHQUAKE, OTHER) |

**Estados terminales:** `RESOLVED` y `CANCELLED` — no se pueden editar ni cerrar de nuevo (error INC03).

#### `incident_students` (pivote N:M incidents ↔ users/alumnos)
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | INT UNSIGNED | PK, AI, UQ | ID |
| incident_id | SMALLINT UNSIGNED | NN, FK→incidents.id | Incidente |
| student_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Alumno (role=alumno) |
| checked_by_user_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Quien verificó el estado |
| status | ENUM('UNKNOWN','PRESENT','MISSING','ABSENT','SAFE') | NN, default 'UNKNOWN' | Estado del alumno |
| checked_at | DATETIME | NN | Cuándo se verificó |
| notes | VARCHAR(255) | — | Notas adicionales |

---

### 3.10 Módulo de Notificaciones

#### `notifications`
| Columna | Tipo | Restricciones | Descripción |
|---------|------|--------------|-------------|
| id | INT UNSIGNED | PK, AI, UQ | ID |
| student_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Alumno relacionado |
| tutor_id | MEDIUMINT UNSIGNED | NN, FK→tutors.id | Tutor familiar |
| user_id | MEDIUMINT UNSIGNED | NN, FK→users.id | Tutor académico o director |
| title | VARCHAR(90) | NN | Título |
| message | VARCHAR(350) | NN | Mensaje |
| type | ENUM('ABSENCE','LATE','INCIDENT','JUSTIFICATION','CLAIM','ANNOUNCEMENT','TEACHER_CLAIM') | NN | Tipo |
| is_read | TINYINT UNSIGNED | NN, default 0 | Si fue leída |
| sent_at | DATETIME | — | Cuándo se envió |

---

## 4. MODELOS ELOQUENT Y RELACIONES

### User
```php
// app/Models/User.php
class User extends Authenticatable
{
    // Relaciones
    public function role(): BelongsTo           // → Role
    public function details(): HasOne           // → UserDetail (1:1)
    public function addresses(): HasMany        // → Address
    public function permissionOverrides(): HasMany // → UserPermissionOverride

    // Como alumno
    public function tutors(): BelongsToMany    // → Tutor via student_tutor
    public function attendances(): HasMany     // → Attendance (student_id)
    public function claims(): HasMany          // → Claim (tutor_id)
    public function justifications(): HasMany  // → Justification (justified_by_user_id)
    public function incidentStudents(): HasMany // → IncidentStudent (student_id)
    public function notifications(): HasMany   // → Notification (student_id o user_id)

    // Como profesor
    public function schedules(): HasMany       // → Schedule (teacher_id)
    public function academicTutor(): HasOne    // → AcademicTutor (user_id) - 1:1

    // Como career_director
    public function managedCareers(): HasMany  // → Career (director_id)
    public function reportedIncidents(): HasMany // → Incident (reported_by_user_id)
    public function reviewedIncidents(): HasMany // → Incident (reviewed_by_user_id)

    // Scopes
    public function scopeActive($query)         // where active = 1
    public function scopeByRole($query, $role)  // where role_id = roles.id where name = $role
    public function scopeVerified($query)       // where verified_at IS NOT NULL
}
```

### Role
```php
public function users(): HasMany  // → User
public function permissionGroups(): BelongsToMany // → PermissionGroup via role_permission_group
```

### UserDetail
```php
public function user(): BelongsTo  // → User (1:1)
// Campos: nfc_uid, qr_uuid
```

### Career
```php
public function director(): BelongsTo  // → User (director_id)
public function groups(): HasMany      // → Group
```

### Group
```php
public function schoolYear(): BelongsTo    // → SchoolYear
public function career(): BelongsTo        // → Career
public function schedules(): HasMany       // → Schedule
public function academicTutors(): BelongsToMany // → AcademicTutor via group_academic_tutor
public function students(): HasMany        // → User (role=alumno) via schedules
```

### Schedule
```php
public function schoolYear(): BelongsTo    // → SchoolYear
public function group(): BelongsTo         // → Group
public function subject(): BelongsTo       // → Subject
public function teacher(): BelongsTo       // → User (teacher_id)
public function classroom(): BelongsTo     // → Classroom
public function settings(): HasOne         // → AttendanceSettings (1:1)
public function attendances(): HasMany     // → Attendance
public function incidents(): HasMany       // → Incident
```

### AcademicTutor
```php
public function user(): BelongsTo          // → User (1:1)
public function groups(): BelongsToMany    // → Group via group_academic_tutor
    // withPivot: ['is_active', 'assigned_at']
```

### Tutor (familiar externo)
```php
public function students(): BelongsToMany // → User via student_tutor
    // withPivot: ['relationship', 'is_primary', 'receives_notifications']
public function notificationPreferences(): HasOne // → NotificationPreferences
public function addresses(): HasMany       // → Address (tutors_id)
public function notifications(): HasMany   // → Notification (tutor_id)
public function claims(): HasMany          // → Claim (tutor_id) — NO, tutor_id en claims es users.id
```

### Attendance
```php
public function student(): BelongsTo    // → User (student_id)
public function schedule(): BelongsTo   // → Schedule
public function device(): BelongsTo     // → Device (devices_id)
public function justification(): HasOne // → Justification
public function claims(): HasMany       // → Claim
```

### Incident
```php
public function reporter(): BelongsTo           // → User (reported_by_user_id)
public function reviewer(): BelongsTo           // → User (reviewed_by_user_id)
public function schedule(): BelongsTo           // → Schedule
public function students(): BelongsToMany       // → User via incident_students
    // withPivot: ['status', 'checked_at', 'notes', 'checked_by_user_id']
```

### Notification
```php
public function student(): BelongsTo    // → User (student_id)
public function tutor(): BelongsTo      // → Tutor (tutor_id)
public function user(): BelongsTo       // → User (user_id) — tutor académico o administrator
```

---

## 5. MIGRACIONES — ORDEN OBLIGATORIO

Respetar este orden para evitar errores de FK:

```
1.  roles
2.  users
3.  user_details
4.  permissions
5.  permission_groups
6.  permission_group_permission
7.  user_permission_overrides
8.  school_years
9.  careers
10. groups
11. subjects
12. classroom
13. devices
14. schedules
15. attendance_settings
16. tutors
17. student_tutor
18. notification_preferences
19. academic_tutors
20. group_academic_tutor
21. attendances
22. justifications
23. claims
24. incidents
25. incident_students
26. notifications
27. addresses
```

---

## 6. ESTRUCTURA DE RESPUESTAS DE LA API

**Toda respuesta de la API debe seguir este contrato.** Usar los helpers `apiSuccess()` y `apiError()`.

### Éxito (2xx)
```json
{
  "success": true,
  "message": "Mensaje amigable al usuario",
  "data": { ... },
  "meta": {
    "timestamp": "2026-06-28T12:00:00Z"
  }
}
```

### Error (4xx / 5xx)
```json
{
  "success": false,
  "message": "Mensaje amigable al usuario",
  "error_code": "AUTH01",
  "errors": {
    "campo": ["Detalle de validación"]
  },
  "meta": {
    "timestamp": "2026-06-28T12:00:00Z"
  }
}
```
> `errors` solo está presente en errores de validación (422 / VAL01).

### Respuesta paginada (200)
```json
{
  "success": true,
  "message": "...",
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "meta": {
    "timestamp": "..."
  }
}
```

### Reglas de respuesta
- `message` siempre presente, nunca expone detalles internos de Laravel.
- `data` es `null` en respuestas sin cuerpo (DELETE, logout).
- `error_code` siempre presente en errores para que el frontend tome acciones específicas.
- Zona horaria de timestamps: `America/Matamoros`.
- Paginación: 20 elementos por página por defecto.

---

## 7. CATÁLOGO DE ERRORES

### Nomenclatura
Formato: `PREFIJO` + `NÚMERO` (ej. `AUTH01`, `VAL01`).
Los códigos son fijos y nunca se reasignan.

### Severidades
- **LOW**: Información o advertencia menor, sin impacto en flujo.
- **MEDIUM**: Error de negocio, el usuario debe corregir su acción.
- **HIGH**: Error de acceso o conflicto de estado, requiere atención.
- **CRITICAL**: Fallo del sistema o BD, requiere intervención inmediata.

---

### AUTH — Autenticación
| Código | Severidad | HTTP | Endpoint(s) | Mensaje Usuario | Causa técnica |
|--------|-----------|------|------------|-----------------|---------------|
| AUTH01 | MEDIUM | 401 | POST /auth/login | Credenciales incorrectas. | Email no encontrado o `Hash::check()` falló. |
| AUTH02 | HIGH | 403 | POST /auth/login | No tienes permiso para acceder a este portal. | `users.role_id` no corresponde al portal. |
| AUTH03 | HIGH | 403 | POST /auth/login | Tu cuenta está desactivada. Contacta al administrador. | `users.active = 0`. |
| AUTH04 | MEDIUM | 403 | POST /auth/login | Tu cuenta aún no ha sido verificada. Revisa tu correo. | `users.verified_at IS NULL`. |
| AUTH05 | MEDIUM | 401 | Cualquier endpoint protegido | Tu sesión ha expirado. Inicia sesión nuevamente. | Token ausente, expirado, revocado o malformado en `personal_access_tokens`. |

### VAL — Validación
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| VAL01 | LOW | 422 | Datos inválidos. Revisa los campos marcados. | Fallo en `FormRequest::rules()`. Los campos con error van en `errors`. |
| VAL02 | LOW | 422 | El rango de fechas es inválido. | `date_to < date_from` o formato incorrecto. |
| VAL03 | MEDIUM | 422 | Debes confirmar la acción para continuar. | Campo `confirm` ausente o ≠ `true` en DELETE. |

### PERM — Permisos
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| PERM01 | HIGH | 403 | No tienes acceso a este recurso. | El recurso existe pero `auth()->id()` no tiene relación válida con él. |
| PERM02 | HIGH | 403 | No puedes crear un reclamo en una materia que no cursas. | `subject_id` no está en los schedules del grupo del alumno. |
| PERM03 | MEDIUM | 404 | El permiso indicado no existe. | `permissions.id` no encontrado. |
| PERM04 | MEDIUM | 409 | Ya existe una regla de permiso individual para este usuario y permiso. | UNIQUE violation en `user_permission_overrides(user_id, permission_id)`. |
| PERM05 | MEDIUM | 404 | La regla de permiso solicitada no existe. | `user_permission_overrides.id` no encontrado. |

### USR — Usuarios
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| USR01 | MEDIUM | 404 | El usuario solicitado no existe. | `users.id` no encontrado o `role_id` no corresponde al tipo esperado. |
| USR02 | MEDIUM | 404 | Tarjeta NFC no reconocida. | `user_details.nfc_uid` no encontrado para el UID leído. |
| USR03 | MEDIUM | 404 | El usuario indicado como director no existe o no tiene el rol correcto. | `director_id` no encontrado o `roles.name ≠ 'career_director'` (FK en careers apunta a usuarios con rol career_director). |
| USR04 | MEDIUM | 409 | Ya existe un usuario registrado con ese correo. | UNIQUE violation en `users.email`. |
| USR05 | HIGH | 409 | No se puede desactivar a un profesor con horarios activos asignados. | Existen `schedules` con `teacher_id = id AND is_active = 1`. |

### GRP — Grupos
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| GRP01 | MEDIUM | 404 | El año escolar indicado no existe. | `school_years.id` no encontrado. |
| GRP02 | MEDIUM | 404 | El grupo solicitado no existe. | `groups.id` no encontrado. |
| GRP03 | MEDIUM | 409 | Ya existe un grupo con ese grado y sección en la misma carrera y ciclo. | UNIQUE violation en `groups(school_year_id, career_id, grade, section)`. |
| GRP04 | HIGH | 409 | No se puede desactivar un grupo con alumnos activos asignados. | Existen `users` con `role = alumno AND group relacionado AND active = 1`. |

### SES — Sesiones de Lista
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| SES01 | MEDIUM | 409 | Ya existe una sesión abierta para esta clase en la fecha indicada. | UNIQUE violation en `attendance_sessions(schedule_id, date)`. |
| SES02 | MEDIUM | 404 | La sesión de clase no existe o ya fue cerrada. | `attendance_sessions.id` no encontrado o `status = CLOSED`. |
| SES03 | MEDIUM | 409 | La sesión ya fue cerrada anteriormente. | `attendance_sessions.status = CLOSED`. |

### ATT — Asistencias
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| ATT01 | LOW | 409 | Este alumno ya registró su asistencia en esta sesión. | UNIQUE violation en `attendance_records(session_id, student_id)`. |
| ATT02 | HIGH | 403 | Este alumno no pertenece al grupo de esta clase. | El `student_id` resuelto desde `nfc_uid` no está inscrito en el grupo del schedule. |
| ATT03 | MEDIUM | 404 | El registro de asistencia indicado no existe. | `attendances.id` no encontrado o no pertenece al alumno autenticado. |
| ATT04 | MEDIUM | 409 | Esta asistencia no puede ser justificada. | `attendances.status ≠ ABSENT`. Solo se justifican inasistencias. |

### INC — Incidentes
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| INC01 | MEDIUM | 404 | El incidente solicitado no existe. | `incidents.id` no encontrado. |
| INC02 | MEDIUM | 409 | El incidente ya fue cerrado y no se puede modificar. | `incidents.status = CLOSED`. |
| INC03 | MEDIUM | 409 | No se puede editar o cerrar un incidente ya cerrado o cancelado. | `incidents.status IN (RESOLVED, CANCELLED)`. |

### CLM — Reclamaciones
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| CLM01 | MEDIUM | 404 | La reclamación solicitada no existe. | `claims.id` no encontrado. |
| CLM02 | MEDIUM | 409 | Esta reclamación ya fue resuelta o rechazada. | `claims.status IN (RESOLVED, REJECTED)`. |

### JUST — Justificantes
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| JUST01 | MEDIUM | 404 | El justificante solicitado no existe. | `justifications.id` no encontrado. |
| JUST02 | MEDIUM | 409 | Este justificante ya fue revisado. | `justifications.status IN (APPROVED, REJECTED)`. |
| JUST03 | MEDIUM | 409 | Ya existe un justificante para esta inasistencia. | UNIQUE violation en `justifications(attendance_id)`. |

### SY — Ciclo Escolar
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| SY01 | MEDIUM | 404 | El ciclo escolar solicitado no existe. | `school_years.id` no encontrado. |
| SY02 | MEDIUM | 409 | Ya existe un ciclo escolar registrado con ese nombre. | UNIQUE violation en `school_years.name`. |

### CAR — Carreras
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| CAR01 | MEDIUM | 404 | La carrera solicitada no existe. | `careers.id` no encontrado. |
| CAR02 | MEDIUM | 409 | Ya existe una carrera registrada con ese código. | UNIQUE violation en `careers.code`. |
| CAR03 | HIGH | 409 | No se puede desactivar una carrera con grupos activos asignados. | Existen `groups` con `career_id = id AND is_active = 1`. |

### SUBJ — Materias
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| SUBJ01 | MEDIUM | 404 | La materia solicitada no existe. | `subjects.id` no encontrado. |
| SUBJ02 | MEDIUM | 409 | Ya existe una materia registrada con ese código. | UNIQUE violation en `subjects.code`. |
| SUBJ03 | HIGH | 409 | No se puede desactivar una materia con horarios activos asignados. | Existen `schedules` con `subject_id = id AND is_active = 1`. |

### DEV — Dispositivos
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| DEV01 | MEDIUM | 404 | El dispositivo solicitado no existe. | `devices.id` no encontrado. |
| DEV02 | HIGH | 503 | El dispositivo no respondió. Verifica su conexión. | Petición HTTP al ESP32 excedió timeout (5s) o recibió código ≠ 200. |
| DEV03 | MEDIUM | 409 | Ya existe un dispositivo registrado con esa dirección MAC. | UNIQUE violation en `devices.mac_address`. |
| DEV04 | LOW | 409 | El dispositivo ya se encuentra dado de baja. | `devices.is_active = 0`. |

### NOT — Notificaciones
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| NOT01 | MEDIUM | 404 | La notificación solicitada no existe. | `notifications.id` no encontrado. |
| NOT02 | MEDIUM | 422 | Debes indicar al menos un destinatario válido. | `target` requiere `student_ids`, `group_ids` o `career_ids` según el tipo. |

### FILE — Archivos
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| FILE01 | MEDIUM | 413 | El archivo supera el límite de 5 MB. | Payload supera `upload_max_filesize`. Configurar en Nginx `client_max_body_size`. |
| FILE02 | MEDIUM | 415 | Tipo de archivo no permitido. Solo JPG, PNG o PDF. | MIME type no está en `['image/jpeg','image/png','application/pdf']`. Validar con `mimetypes`, no con extensión. |

### TUT — Tutores Familiares
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| TUT01 | MEDIUM | 404 | El tutor solicitado no existe o no está asignado a este alumno. | `tutors.id` no encontrado o `student_tutor(student_id, tutor_id)` no existe. |
| TUT02 | HIGH | 409 | No puedes eliminar al único tutor del alumno. Asigna otro primero. | `COUNT(student_tutor WHERE student_id = id) = 1`. |

### CLS — Salones
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| CLS01 | MEDIUM | 404 | El salón indicado no existe. | `classroom.id` no encontrado. |

### LOG — Auditoría
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| LOG01 | MEDIUM | 404 | El registro solicitado no existe. | `audit_logs.id` no encontrado o no pertenece a la carrera del director. |

### SRV — Servidor
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| SRV01 | CRITICAL | 500 | Error interno del servidor. Intenta de nuevo más tarde. | `Throwable` no capturado. Ver `storage/logs/laravel.log`. |
| SRV02 | HIGH | 404 | El recurso solicitado no existe. | `ModelNotFoundException` de Eloquent. Preferir errores específicos (USR01, GRP02...). |

### DB — Base de Datos
| Código | Severidad | HTTP | Mensaje Usuario | Causa técnica |
|--------|-----------|------|-----------------|---------------|
| DB01 | CRITICAL | 500 | Error interno del servidor. Intenta de nuevo más tarde. | `QueryException`. Loguear con `Log::critical` incluyendo SQL y error MySQL. |
| DB02 | CRITICAL | 503 | El sistema no está disponible en este momento. | No se pudo conectar a MySQL. Verificar servicio y credenciales en `.env`. |

---

## 8. ENDPOINTS POR ROL

Base URL: `/api/v1`
Auth: `Authorization: Bearer {token}` en todas las rutas protegidas.

---

### 8.0 AUTENTICACIÓN (todos los roles)

#### POST /api/v1/auth/login
**Público — sin auth**
```json
Body: { "email": "string (regex: ^[a-zA-Z0-9._%+-]+@[...]+$)", "password": "string (^.{6,128}$)" }

200: { "data": { "token": "1|abc123...", "token_type": "Bearer", "user": { "id": 42, "first_name": "Carlos", "first_surname": "López", "email": "...", "role": "profesor", "photo": "https://..." } } }
401 AUTH01: Credenciales incorrectas.
403 AUTH02: No tienes permiso para acceder a este portal.
403 AUTH03: Tu cuenta está desactivada. Contacta al administrador.
403 AUTH04: Tu cuenta aún no ha sido verificada. Revisa tu correo.
422 VAL01:  Datos inválidos.
```

#### POST /api/v1/auth/logout
**Requiere: auth:sanctum**
```
Sin body. Revoca el token del header Authorization.
200: Sesión cerrada con éxito.
401 AUTH05: No hay una sesión activa.
```

#### POST /api/v1/auth/refresh
**Requiere: auth:sanctum**
```
Sin body. Revoca el token actual y emite uno nuevo.
200: { "data": { "token": "2|xyz789...", "token_type": "Bearer" } }
401 AUTH05: Tu sesión ha expirado.
```

---

### 8.1 ROL: PROFESOR (`role:profesor`)

#### 👥 Mis Grupos

**GET /api/v1/profesor/groups**
```
Query (opt): school_year_id (^[1-9]\d*$) — default: ciclo ACTIVE
200: [{ "id": 5, "grade": "1", "section": "A", "shift": "MORNING", "career": { "id": 2, "short_name": "TICS" }, "student_count": 27 }]
404 GRP01, 422 VAL01
```

**GET /api/v1/profesor/groups/{id}/students**
```
Path: id (^[1-9]\d*$)
200: [{ "id": 101, "first_name": "Juan", "first_surname": "Ramírez", "second_surname": "Torres", "photo": "https://..." }]
403 PERM01, 404 GRP02
```

**GET /api/v1/profesor/students/{id}**
```
Path: id — ID del alumno (users.id)
200: { "id": 101, "first_name": "Juan", "second_name": "Carlos", "first_surname": "Ramírez", "second_surname": "Torres", "email": "...", "phone": "8711234567", "birth_date": "2005-03-15", "gender": "M", "photo": "https://...", "group": { "id": 5, "grade": "1", "section": "A" }, "career": { "id": 2, "name": "Tecnologías de la Información" } }
403 PERM01, 404 USR01
```

**GET /api/v1/profesor/students/{id}/attendance**
```
Query (opt): date_from (^\d{4}-\d{2}-\d{2}$), date_to, type (^(ON_TIME|LATE|ABSENT|JUSTIFIED)$), subject_id
200: [{ "date": "2026-06-10", "subject": { "id": 3, "name": "Base de Datos" }, "status": "ON_TIME", "checked_in_at": "2026-06-10T17:02:34Z" }]
403 PERM01, 422 VAL02
```

**GET /api/v1/profesor/students/{id}/justifications**
```
200: [{ "id": 8, "date": "2026-05-20", "subject": { "id": 3, "name": "Base de Datos" }, "status": "APPROVED", "evidence_url": "https://..." }]
403 PERM01, 404 USR01
```

#### 📅 Horario y Pasar Lista

**GET /api/v1/profesor/schedule/today**
```
Sin params. Zona horaria: America/Matamoros.
200: [{ "schedule_id": 12, "subject": { "id": 3, "name": "Base de Datos" }, "group": { "id": 5, "grade": "1", "section": "A" }, "classroom": { "name": "Aula 201", "building": "B" }, "start_time": "17:00:00", "end_time": "18:40:00", "session_open": false }]
```

**GET /api/v1/profesor/schedule**
```
Query (opt): day (^(MONDAY|TUESDAY|WEDNESDAY|THURSDAY|FRIDAY|SATURDAY|SUNDAY)$)
200: { "MONDAY": [...], "TUESDAY": [...] }
422 VAL01
```

**POST /api/v1/profesor/sessions/open**
```
Body: { "schedule_id": int (^[1-9]\d*$), "date": "date (^\d{4}-\d{2}-\d{2}$)" }
201: { "session_id": 88, "schedule_id": 12, "date": "2026-06-28", "status": "OPEN", "opened_at": "..." }
403 PERM01, 409 SES01, 422 VAL01
```

**POST /api/v1/profesor/sessions/{id}/nfc**
```
Path: id (session_id)
Body: { "nfc_uid": "string (^[A-Fa-f0-9:\- ]{1,100}$)", "scanned_at": "datetime (^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$)" }
201: { "student_id": 101, "full_name": "Juan Ramírez Torres", "status": "ON_TIME", "checked_in_at": "..." }
404 USR02, 404 SES02, 409 ATT01, 403 ATT02
```

**PATCH /api/v1/profesor/sessions/{id}/students/{sid}**
```
Path: id (session_id), sid (users.id del alumno)
Body: { "status": "enum (^(ON_TIME|LATE|ABSENT)$)" }
200: { "student_id": 101, "status": "ABSENT", "updated_at": "..." }
403 PERM01, 422 VAL01
```

**POST /api/v1/profesor/sessions/{id}/close**
```
Path: id (session_id)
Sin body.
200: { "session_id": 88, "status": "CLOSED", "total_students": 27, "on_time": 20, "late": 3, "absent": 4, "closed_at": "..." }
Acción automática: marca como ABSENT a quienes no registraron y envía notificaciones a tutores.
403 PERM01, 409 SES03
```

#### 🚨 Incidentes

**GET /api/v1/profesor/incidents**
```
Query (opt): type (^(FIRE|GAS|EARTHQUAKE|OTHER)$), date_from, date_to, page
200: [{ "id": 15, "type": "FIRE", "title": "...", "severity": "HIGH", "status": "CLOSED", "created_at": "..." }]
422 VAL01
```

**GET /api/v1/profesor/incidents/active** — Incidentes activos visibles a todos los profesores
```
200: [{ "id": 20, "type": "FIRE", "severity": "CRITICAL", "status": "ACTIVE", "reporter": {...}, "groups": [...] }]
```

**GET /api/v1/profesor/incidents/{id}**
```
200: { "id": 20, "type": "FIRE", "title": "...", "description": "...", "severity": "CRITICAL", "status": "ACTIVE", "reporter": {...}, "groups": [...], "students": [{ "id": 101, "full_name": "...", "present": true }], "evidence_url": "...", "created_at": "..." }
403 PERM01, 404 INC01
```

**POST /api/v1/profesor/incidents** — Content-Type: multipart/form-data
```
Body: type (^(FIRE|GAS|EARTHQUAKE|OTHER)$, req), title (^.{3,120}$, req), description (^[\s\S]{0,500}$, opt), severity (^(LOW|MEDIUM|HIGH|CRITICAL)$, req), group_ids (array<int>, opt), evidence (file jpg|png|pdf ≤5MB, opt)
201: { "id": 21, "type": "GAS", "title": "...", "severity": "HIGH", "status": "ACTIVE", "created_at": "..." }
422 VAL01, 413 FILE01, 415 FILE02
```

**PUT /api/v1/profesor/incidents/{id}** — multipart/form-data, mismos campos que POST, todos opt
```
200: Incidente actualizado.
403 PERM01 (solo puede editar los suyos), 409 INC02, 422 VAL01
```

**PATCH /api/v1/profesor/incidents/{id}/students**
```
Body: { "students": [{ "student_id": int, "present": bool }], "comment": "string (opt, ≤300 chars)" }
200: { "incident_id": 20, "updated_students": 3, "present_count": 24, "absent_count": 3 }
403 PERM01, 409 INC02, 422 VAL01
```

#### 📋 Reclamaciones

**GET /api/v1/profesor/claims**
```
Query (opt): status (^(PENDING|IN_PROGRESS|RESOLVED|REJECTED)$), group_id, page
200: [{ "id": 7, "student": {...}, "subject": {...}, "status": "PENDING", "created_at": "..." }]
422 VAL01
```

**GET /api/v1/profesor/claims/{id}**
```
200: { "id": 7, "student": { "id": 101, "full_name": "...", "group": "1-A TICS" }, "subject": {...}, "description": "...", "evidence_url": "...", "status": "PENDING", "created_at": "..." }
403 PERM01, 404 CLM01
```

---

### 8.2 ROL: PROFESOR TUTOR ACADÉMICO (`role:tutor_academico`)

**Hereda TODOS los endpoints de Profesor.** El middleware cambia de `role:profesor` a `role:tutor_academico`.
Solo se documentan los endpoints adicionales.

#### 📋 Reclamaciones (scope ampliado — todos sus grupos como tutor)

**GET /api/v1/tutor/claims**
```
Query (opt): career_id, group_id, status, page
200: [{ "id": 7, "student": {...}, "group": { "id": 5, "grade": "1", "section": "A" }, "career": {...}, "subject": {...}, "status": "PENDING", "created_at": "..." }]
403 PERM01, 422 VAL01
```

**GET /api/v1/tutor/claims/{id}**
```
200: { "id": 7, ...igual que profesor... , "history": [{ "action": "IN_PROGRESS", "by": "Carlos López", "at": "..." }] }
403 PERM01, 404 CLM01
```

**PATCH /api/v1/tutor/claims/{id}/action** — EXCLUSIVO TUTOR
```
Body: { "action": "enum (^(IN_PROGRESS|CONTACT|RESOLVED|REJECTED)$)", "comment": "string (opt, ≤500 chars)" }
200: { "id": 7, "status": "IN_PROGRESS", "action_by": "Carlos López", "action_at": "...", "comment": "..." }
403 PERM01, 404 CLM01, 409 CLM02, 422 VAL01
```

#### 📄 Justificantes (aprobación) — EXCLUSIVO TUTOR

**PATCH /api/v1/tutor/students/{id}/justifications/{jid}**
```
Path: id (users.id del alumno), jid (justifications.id)
Body: { "status": "enum (^(APPROVED|REJECTED)$)", "comment": "string (opt, ≤300 chars)" }
200: { "justification_id": 8, "student_id": 101, "status": "APPROVED", "reviewed_by": "Carlos López", "reviewed_at": "...", "comment": null }
403 PERM01, 404 USR01, 404 JUST01, 409 JUST02, 422 VAL01
```

---

### 8.3 ROL: ALUMNO (`role:alumno`)

#### 👤 Mi Información

**GET /api/v1/alumno/profile**
```
Sin params. Devuelve datos del alumno autenticado.
200: { "id": 101, "first_name": "Juan", "second_name": "Carlos", "first_surname": "Ramírez", "second_surname": "Torres", "email": "...", "phone": "8711234567", "birth_date": "2005-03-15", "photo": "https://...", "group": { "id": 5, "grade": "1", "section": "A" }, "career": { "id": 2, "name": "Tecnologías de la Información" } }
```

#### 📋 Reclamos

**GET /api/v1/alumno/claims**
```
Query (opt): status (^(PENDING|IN_PROGRESS|RESOLVED|REJECTED)$), page
200: [{ "id": 7, "subject": {...}, "teacher": {...}, "status": "PENDING", "created_at": "..." }]
422 VAL01
```

**GET /api/v1/alumno/claims/{id}**
```
200: { "id": 7, "subject": {...}, "teacher": {...}, "description": "...", "evidence_url": "...", "status": "PENDING", "created_at": "..." }
403 PERM01, 404 CLM01
```

**POST /api/v1/alumno/claims** — multipart/form-data
```
Body: subject_id (^[1-9]\d*$, req), description (^.{10,500}$, req), evidence (file ≤5MB, opt)
201: { "id": 9, "subject": {...}, "status": "PENDING", "created_at": "..." }
403 PERM02, 422 VAL01, 413 FILE01, 415 FILE02
```

#### 📄 Justificantes

**GET /api/v1/alumno/justifications**
```
Query (opt): subject_id, status (^(PENDING|APPROVED|REJECTED)$)
200: [{ "id": 8, "subject": {...}, "date": "2026-05-20", "status": "PENDING", "created_at": "..." }]
422 VAL01
```

**GET /api/v1/alumno/justifications/{id}**
```
200: { "id": 8, "subject": {...}, "date": "...", "reason": "Cita médica", "evidence_url": "...", "status": "PENDING", "reviewed_by": null, "comment": null, "created_at": "..." }
403 PERM01, 404 JUST01
```

#### 📚 Mis Materias

**GET /api/v1/alumno/subjects**
```
Sin params. Materias del ciclo ACTIVE según el grupo del alumno.
200: [{ "id": 3, "name": "Base de Datos", "teacher": { "id": 42, "full_name": "Carlos López" }, "schedule": "Lun-Mié 17:00-18:40" }]
```

**GET /api/v1/alumno/subjects/{id}**
```
200: { "id": 3, "name": "Base de Datos", "teacher": { "id": 42, "full_name": "...", "photo": "..." }, "classroom": "Aula 201", "schedule": "Lun-Mié 17:00-18:40", "attendance_summary": { "on_time": 14, "late": 2, "absent": 1 } }
403 PERM01, 404 SUBJ01
```

**GET /api/v1/alumno/subjects/{id}/attendance**
```
200: [{ "attendance_id": 501, "date": "2026-06-22", "status": "ABSENT", "justifiable": true }, { "attendance_id": 498, "date": "2026-06-17", "status": "ON_TIME", "justifiable": false }]
403 PERM01
```

**POST /api/v1/alumno/subjects/{id}/attendance/{aid}/justify** — multipart/form-data
```
Path: id (subjects.id), aid (attendances.id a justificar)
Body: reason (^.{5,300}$, req), evidence (file ≤5MB, req)
201: { "id": 12, "attendance_id": 501, "subject": {...}, "status": "PENDING", "created_at": "..." }
403 PERM01, 404 ATT03, 409 ATT04, 409 JUST03, 422 VAL01, 413 FILE01, 415 FILE02
```

---

### 8.4 ROL: ADMINISTRADOR ESCOLAR (`role:administrator`)

> ⚠️ En BD este es `role_id` = 4, nombre `administrator`. A nivel de producto es "Administrador Escolar".
> No confundir con el "Director de Carrera" (`career_director`), que es el director asignado a cada carrera.
> El acceso a módulos específicos se controla mediante sub-roles (ver sección de comentarios al final del archivo).

#### 🔔 Notificaciones

**GET /api/v1/administrator/notifications**
```
Query (opt): type, is_read, date_from, date_to, page
200: [{ "id": 55, "title": "...", "type": "ANNOUNCEMENT", "recipient_type": "TUTOR", "is_read": false, "sent_at": "..." }]
422 VAL01
```

**GET /api/v1/administrator/notifications/{id}**
```
200: { "id": 55, "title": "...", "message": "...", "type": "ANNOUNCEMENT", "student": null, "tutor": null, "sent_by": { "id": 3, "full_name": "..." }, "is_read": false, "sent_at": "..." }
404 NOT01
```

**POST /api/v1/administrator/notifications**
```
Body: title (^.{3,90}$), message (^.{1,350}$), type (ENUM notifications.type), target (^(STUDENT|TUTOR|GROUP|CAREER|ALL)$), student_ids (req si target=STUDENT/TUTOR), group_ids (req si target=GROUP), career_ids (req si target=CAREER)
201: { "id": 56, "title": "...", "type": "ANNOUNCEMENT", "recipients_count": 120, "sent_at": "..." }
422 VAL01, 422 NOT02
```

**POST /api/v1/administrator/notifications/{id}/resend**
```
Body (opt): target, student_ids, group_ids
201: { "id": 57, "original_notification_id": 55, "recipients_count": 45, "sent_at": "..." }
404 NOT01, 422 VAL01
```

#### 📡 Dispositivos ESP32

**GET /api/v1/administrator/devices** — Query opt: classroom_id, is_active
**GET /api/v1/administrator/devices/{id}** — 404 DEV01
**GET /api/v1/administrator/devices/{id}/ping** — HTTP request al ESP32, timeout 5s → 200 ONLINE / 503 DEV02

**POST /api/v1/administrator/devices**
```
Body: mac_address (^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$, req), ip (opt), classroom_id (req)
201: { "id": 9, "mac_address": "...", "ip": "...", "is_active": true, "classroom_id": 12 }
409 DEV03, 404 CLS01, 422 VAL01
```

**PUT /api/v1/administrator/devices/{id}** — Body opt: ip, classroom_id, is_active
**DELETE /api/v1/administrator/devices/{id}** — Soft delete (is_active=0) → 409 DEV04 si ya estaba baja

#### 🎓 Carreras

**GET /api/v1/administrator/careers** — Query opt: include_inactive
**GET /api/v1/administrator/careers/{id}** — incluye subjects y groups_count

**POST /api/v1/administrator/careers**
```
Body: name (^.{3,150}$), short_name (opt, ^.{1,20}$), code (^[A-Z0-9\-]{2,30}$), director_id (opt, FK→users career_director)
201: { "id": 5, "name": "...", "short_name": "IER", "code": "IER-01", "is_active": true }
409 CAR02, 404 USR03, 422 VAL01
```

**PUT /api/v1/administrator/careers/{id}** — mismos campos, todos opt
**DELETE /api/v1/administrator/careers/{id}** — Body: { "confirm": true } → 409 CAR03 si tiene grupos activos

#### 📆 Ciclo Escolar

**GET /api/v1/administrator/school-years** — Query opt: status
**GET /api/v1/administrator/school-years/{id}** — incluye groups_count

**POST /api/v1/administrator/school-years**
```
Body: name (^\d{4}-\d{4}$), start_date (^\d{4}-\d{2}-\d{2}$), end_date (debe ser > start_date)
Crea con status='UPCOMING'.
201 | 409 SY02 | 422 VAL02
```

**PUT /api/v1/administrator/school-years/{id}** — Body opt: name, start_date, end_date, status
> Al cambiar status a ACTIVE, el ciclo anterior pasa automáticamente a FINISHED.

#### 📘 Materias

**GET /api/v1/administrator/subjects** — Query opt: career_id, search, is_active
**GET /api/v1/administrator/subjects/{id}** — incluye schedules_count

**POST /api/v1/administrator/subjects**
```
Body: name (^.{3,100}$), code (^[A-Z0-9\-]{2,30}$), description (opt, ≤255)
409 SUBJ02, 422 VAL01
```

**PUT /api/v1/administrator/subjects/{id}** — mismos campos, todos opt
**DELETE /api/v1/administrator/subjects/{id}** — Body: { "confirm": true } → 409 SUBJ03

#### 👥 Grupos

**GET /api/v1/administrator/groups** — Query opt: career_id, school_year_id, shift, is_active
**GET /api/v1/administrator/groups/{id}** — incluye academic_tutors

**POST /api/v1/administrator/groups**
```
Body: school_year_id, career_id, grade (^[A-Za-z0-9]{1,5}$), section (^[A-Za-z0-9]{1,5}$), shift (opt)
409 GRP03, 404 CAR01, 404 SY01, 422 VAL01
```

**PUT /api/v1/administrator/groups/{id}** — mismos campos, todos opt
**DELETE /api/v1/administrator/groups/{id}** — Body: { "confirm": true } → 409 GRP04

#### 🧑‍🏫 Usuarios — Profesores

**GET /api/v1/administrator/teachers** — Query opt: search, is_academic_tutor, active, page
**GET /api/v1/administrator/teachers/{id}** — incluye tutored_groups, schedules_count

**POST /api/v1/administrator/teachers** — multipart/form-data
```
Fields: first_name (^[A-Za-zÀ-ÿ\s]{2,45}$), second_name (opt), first_surname, second_surname, email (regex email), phone (^\d{10}$), birth_date, gender (^(M|F|OTRO)$), photo (file jpg/png ≤3MB, opt), is_academic_tutor (opt)
Genera contraseña temporal y la envía por correo. role_id = Profesor, active = 1.
201 | 409 USR04 | 422 VAL01 | 415 FILE02
```

**PUT /api/v1/administrator/teachers/{id}** — mismos campos, todos opt
**DELETE /api/v1/administrator/teachers/{id}** — Body: { "confirm": true } → 409 USR05

**PATCH /api/v1/administrator/teachers/{id}/academic-tutor**
```
Body: { "is_active": bool, "group_ids": [int] (opt, sincroniza group_academic_tutor) }
200: { "teacher_id": 42, "is_academic_tutor": true, "groups": [{ "id": 5, "grade": "1", "section": "A" }] }
404 USR01, 404 GRP02, 422 VAL01
```

#### 🎒 Usuarios — Alumnos

**GET /api/v1/administrator/students** — Query opt: search, group_id, career_id, active, page
**GET /api/v1/administrator/students/{id}** — incluye address, tutors

**POST /api/v1/administrator/students** — multipart/form-data
```
Alumno: first_name, first_surname, second_surname, email, phone, birth_date, gender, group_id, photo (opt)
Tutor principal (requerido): tutor_first_name, tutor_first_surname, tutor_phone, tutor_relationship
Crea: user (role=alumno), tutors record, student_tutor (is_primary=1), notification_preferences (todo=1).
201 | 409 USR04 | 404 GRP02 | 422 VAL01
```

**PUT /api/v1/administrator/students/{id}** — mismos campos del alumno, todos opt (no incluye tutor)
**DELETE /api/v1/administrator/students/{id}** — Body: { "confirm": true }

**POST /api/v1/administrator/students/{id}/tutors**
```
Body: first_name, second_name (opt), first_surname, second_surname, phone (^\d{10}$), relationship (^.{2,50}$), is_primary (opt), receives_notifications (opt, default true)
Si is_primary=true, el anterior principal pierde esa bandera.
201 | 404 USR01 | 422 VAL01
```

**PUT /api/v1/administrator/students/{id}/tutors/{tid}** — mismos campos, todos opt
**DELETE /api/v1/administrator/students/{id}/tutors/{tid}** → 409 TUT02 si es el único tutor

#### 🔑 Permisos de Usuario

**GET /api/v1/administrator/users/permissions** — Query opt: search, role_id, has_overrides, page
**GET /api/v1/administrator/users/{id}/permissions** — Retorna role_permissions + overrides

**POST /api/v1/administrator/users/{id}/permissions/override**
```
Body: { "permission_id": int, "type": "enum (^(ALLOW|DENY)$)" }
201 | 404 USR01 | 404 PERM03 | 409 PERM04 | 422 VAL01
```

**DELETE /api/v1/administrator/users/{id}/permissions/override/{oid}** — 404 PERM05

---

### 8.5 ROL: DIRECTOR DE CARRERA (`role:career_director`)

Solo consulta e inteligencia de datos. Sin CRUD de catálogos maestros.
Scope limitado a la carrera donde `careers.director_id = auth()->id()`.

#### 👥 Mis Grupos

**GET /api/v1/career-director/groups** — Query opt: school_year_id, shift
**GET /api/v1/career-director/groups/{id}** — incluye academic_tutor, attendance_summary → 403 PERM01
**GET /api/v1/career-director/groups/{id}/students** → 403 PERM01
**GET /api/v1/career-director/groups/{id}/schedule** — retorna horario completo con profesor y salón
**GET /api/v1/career-director/students/{id}** → 403 PERM01 | 404 USR01
**GET /api/v1/career-director/students/{id}/attendance** — Query opt: date_from, date_to
**GET /api/v1/career-director/students/{id}/justifications**

#### 🧑‍🏫 Profesores (solo lectura)

**GET /api/v1/career-director/teachers** — Profesores que imparten en la carrera
**GET /api/v1/career-director/teachers/{id}** — incluye schedules → 403 PERM01 | 404 USR01
**GET /api/v1/career-director/teachers/{id}/class-attendance** — Query opt: schedule_id, date_from, date_to
```
Determina si el profesor abrió la sesión de lista en cada clase de su horario.
200: [{ "date": "...", "group": "1-A", "subject": "Base de Datos", "scheduled_start": "17:00:00", "session_opened": true, "opened_at": "17:03:12" }]
403 PERM01, 422 VAL02
```

#### 🚨 Incidentes (CRUD completo en su carrera + cierre)

**GET /api/v1/career-director/incidents** — Query opt: status, severity, date_from, date_to, page
**GET /api/v1/career-director/incidents/active**
**GET /api/v1/career-director/incidents/{id}** — incluye students con status
**POST /api/v1/career-director/incidents** — multipart/form-data (igual que profesor + schedule_id + student_ids)
**PUT /api/v1/career-director/incidents/{id}** — 409 INC03

**PATCH /api/v1/career-director/incidents/{id}/students**
```
Body: { "students": [{ "student_id": int, "status": "UNKNOWN|PRESENT|MISSING|ABSENT|SAFE", "notes": "opt" }] }
200: { "incident_id": 20, "updated_count": 2 }
```

**POST /api/v1/career-director/incidents/{id}/close**
```
Body: { "resolution": "^(RESOLVED|CANCELLED)$", "notes": "opt" }
Registra al director en reviewed_by_user_id.
200: { "id": 20, "status": "RESOLVED", "reviewed_by": "Lic. Ana Martínez" }
409 INC03
```

#### 📋 Reclamaciones (claims.director_id = auth()->id())

**GET /api/v1/career-director/claims** — Query opt: status, group_id, page
**GET /api/v1/career-director/claims/{id}**

**PATCH /api/v1/career-director/claims/{id}/action**
```
Body: { "action": "^(IN_PROGRESS|CONTACT|RESOLVED|REJECTED)$", "comment": "opt" }
200: { "id": 7, "status": "...", "action_by": "...", "action_at": "..." }
409 CLM02
```

#### 📊 Gráficas

**GET /api/v1/career-director/charts/general** — Resumen general de asistencia de la carrera
**GET /api/v1/career-director/charts/incidents** — Estadísticas de incidentes
**GET /api/v1/career-director/charts/absences** — Tendencias de inasistencias por grupo/materia
**GET /api/v1/career-director/charts/justifications** — Estado de justificantes

#### 📡 Dispositivos (solo lectura)

**GET /api/v1/career-director/devices** — Dispositivos de salones en grupos de su carrera
**GET /api/v1/career-director/devices/{id}**
**GET /api/v1/career-director/devices/{id}/ping** — Igual que admin → 503 DEV02

#### 📋 Registros de Auditoría

**GET /api/v1/career-director/logs/students** — Logs de cambios en alumnos de su carrera
**GET /api/v1/career-director/logs/devices** — Logs de dispositivos
**GET /api/v1/career-director/logs/groups** — Logs de grupos
**GET /api/v1/career-director/logs/teachers** — Logs de profesores

**GET /api/v1/career-director/logs/{id}**
```
200: { "id": 901, "entity": "student", "entity_id": 101, "action": "UPDATE", "performed_by": { "id": 3, "full_name": "..." }, "before": { "group_id": 4 }, "after": { "group_id": 5 }, "created_at": "..." }
404 LOG01
```

---

## 9. REGLAS DE NEGOCIO CRÍTICAS

1. **Un alumno siempre debe tener al menos un tutor familiar.** No se puede eliminar si es el único (TUT02).

2. **Solo puede haber un ciclo escolar ACTIVE.** Al activar uno, el anterior cambia a FINISHED automáticamente.

3. **Soft deletes con `is_active`.** Nunca usar `deleted_at`. Los registros desactivados no se eliminan físicamente.

4. **Las contraseñas se generan automáticamente** al registrar profesores y alumnos. Se envían por correo (Laravel Mailer). El usuario con `active = 0` o `verified_at IS NULL` no puede hacer login.

5. **Scope por carrera para career_director.** Todas sus consultas se filtran por `careers.director_id = auth()->id()`. Nunca devolver datos de otras carreras.

6. **El tutor académico hereda permisos de profesor.** El middleware `role:tutor_academico` debe incluir todos los permisos de `role:profesor`.

7. **Al cerrar una sesión de lista (`POST /sessions/{id}/close`):** todos los alumnos sin registro quedan como `ABSENT` y se envían notificaciones automáticas a sus tutores familiares.

8. **Solo se pueden justificar asistencias con `status = ABSENT`.** Los retardos (`LATE`) no son justificables (ATT04).

9. **Los DELETEs con impacto en cascada requieren `{ "confirm": true }` en el body** (carreras, materias, grupos, usuarios). Validar con VAL03 si no viene.

10. **Archivos:** Solo JPG, PNG, PDF. Máximo 5 MB (fotos de usuario: 3 MB). Validar MIME type real, no extensión.

11. **Zona horaria:** `America/Matamoros` en todo el sistema. Configurar en `config/app.php` y en las queries de fecha.

12. **Las `notification_preferences` se crean automáticamente** con todos los campos en `1` al registrar un alumno.

---

## 10. CONFIGURACIÓN DEL PROYECTO

```php
// config/app.php
'timezone' => 'America/Matamoros',

// .env mínimo
APP_KEY=...
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=checkmate
DB_USERNAME=...
DB_PASSWORD=...
SANCTUM_STATEFUL_DOMAINS=...
MAIL_MAILER=smtp
```

### Middlewares a registrar
```php
// bootstrap/app.php o Kernel.php
'role:profesor'         → verifica role_id = profesor
'role:tutor_academico'  → verifica role_id = tutor_academico (incluye permisos de profesor)
'role:alumno'           → verifica role_id = alumno
'role:administrator'    → verifica role_id = administrator (Admin Escolar) + sub-rol de módulo
'role:career_director'  → verifica role_id = career_director + scope a su carrera
```

### Rutas
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // Públicas
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protegidas
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);

        // Profesor
        Route::middleware('role:profesor')->prefix('profesor')->group(function () { ... });

        // Tutor Académico
        Route::middleware('role:tutor_academico')->prefix('tutor')->group(function () { ... });

        // Alumno
        Route::middleware('role:alumno')->prefix('alumno')->group(function () { ... });

        // Administrador Escolar
        Route::middleware('role:administrator')->prefix('administrator')->group(function () { ... });

        // Director de Carrera
        Route::middleware('role:career_director')->prefix('career-director')->group(function () { ... });
    });
});
```



---

## 11. SUB-ROLES DEL ADMINISTRATOR Y PERMISOS INDIVIDUALES

> Esta sección documenta las decisiones de diseño sobre el control de acceso granular.
> El sistema RBAC existente (`permission_groups`, `permissions`, `user_permission_overrides`) ya provee la infraestructura necesaria.

---

### 11.1 Sub-roles del Administrator

El rol `administrator` (role_id = 4) concentra todos los CRUDs del sistema.
Los sub-roles permiten restringir a qué módulos tiene acceso cada administrador escolar.
Se implementan como `permission_groups` asignados individualmente a cada usuario con rol `administrator`.

**Reglas:**
- El sub-rol es **opcional al crear** el usuario. Se puede asignar en ese momento o posteriormente.
- Un administrador **sin sub-rol asignado no tiene acceso** a ningún módulo (solo puede autenticarse).
- `administrator.full` es un **permission_group especial** con `key_name = 'administrator.full'` que otorga acceso a todos los módulos con un solo registro, sin necesidad de asignar cada grupo individualmente.
- Un administrador puede tener **múltiples sub-roles** simultáneamente (ej. alumnos + profesores).

**permission_groups para administrator:**

```
key_name                     Módulos que habilita
─────────────────────────────────────────────────────────────────────
administrator.full           Acceso total a todos los módulos (permiso especial)
administrator.students       /administrator/students/* y /administrator/students/{id}/tutors/*
administrator.teachers       /administrator/teachers/* y PATCH academic-tutor
administrator.careers        /administrator/careers/* y /administrator/school-years/*
administrator.subjects       /administrator/subjects/*
administrator.groups         /administrator/groups/*
administrator.devices        /administrator/devices/*
administrator.notifications  /administrator/notifications/*
administrator.permissions    /administrator/users/permissions/*
```

**Implementación en rutas:**
```php
// El middleware 'admin.module' verifica:
// 1. role_id = administrator
// 2. Tiene permission_group con key_name = 'administrator.{modulo}' OR 'administrator.full'

Route::middleware(['role:administrator', 'admin.module:students'])
    ->prefix('administrator/students')
    ->group(function () { ... });

Route::middleware(['role:administrator', 'admin.module:teachers'])
    ->prefix('administrator/teachers')
    ->group(function () { ... });
// etc.
```

---

### 11.2 Permisos individuales por rol (key_name de permissions)

Todos los roles del sistema (incluidos `career_director` y `administrator`) pueden tener
permisos individuales habilitados (ALLOW) o revocados (DENY) via `user_permission_overrides`.
Los `career_director` **no tienen sub-roles** — solo overrides individuales sobre su rol base.

A continuación se define la lista de `permissions` por módulo funcional:

#### Módulo: Alumnos (alumno)
```
key_name                          Descripción                              Endpoint(s)
────────────────────────────────────────────────────────────────────────────────────────────
alumno.profile.view               Ver perfil propio                        GET /alumno/profile
alumno.subjects.view              Ver materias actuales                    GET /alumno/subjects, GET /alumno/subjects/{id}
alumno.attendance.view            Ver asistencias propias por materia      GET /alumno/subjects/{id}/attendance
alumno.justification.create       Crear justificante de inasistencia       POST /alumno/subjects/{id}/attendance/{aid}/justify
alumno.justification.view         Ver lista y detalle de justificantes     GET /alumno/justifications, GET /alumno/justifications/{id}
alumno.claim.create               Crear reclamo contra un profesor         POST /alumno/claims
alumno.claim.view                 Ver lista y detalle de reclamos propios  GET /alumno/claims, GET /alumno/claims/{id}
```

#### Módulo: Profesor (profesor)
```
key_name                          Descripción                              Endpoint(s)
────────────────────────────────────────────────────────────────────────────────────────────
profesor.groups.view              Ver grupos y alumnos asignados           GET /profesor/groups, GET /profesor/groups/{id}/students
profesor.student.view             Ver detalle, asistencia y justif. alumno GET /profesor/students/{id}/*
profesor.schedule.view            Ver horario propio                       GET /profesor/schedule, GET /profesor/schedule/today
profesor.session.open             Abrir sesión de lista (iniciar clase)    POST /profesor/sessions/open
profesor.session.nfc              Registrar asistencia por NFC             POST /profesor/sessions/{id}/nfc
profesor.session.manual           Corregir asistencia manualmente          PATCH /profesor/sessions/{id}/students/{sid}
profesor.session.close            Cerrar sesión de lista                   POST /profesor/sessions/{id}/close
profesor.incident.view            Ver incidentes propios y activos         GET /profesor/incidents, GET /profesor/incidents/{id}
profesor.incident.create          Crear incidente de emergencia            POST /profesor/incidents
profesor.incident.edit            Editar incidente propio activo           PUT /profesor/incidents/{id}
profesor.incident.students        Actualizar lista de emergencia           PATCH /profesor/incidents/{id}/students
profesor.claim.view               Ver reclamaciones de sus materias        GET /profesor/claims, GET /profesor/claims/{id}
```

#### Módulo: Tutor Académico (tutor_academico — hereda todos los de profesor)
```
key_name                          Descripción                              Endpoint(s)
────────────────────────────────────────────────────────────────────────────────────────────
tutor.claims.view                 Ver reclamaciones de todos sus grupos    GET /tutor/claims, GET /tutor/claims/{id}
tutor.claims.action               Ejecutar acción sobre reclamación        PATCH /tutor/claims/{id}/action
tutor.justification.review        Aprobar o rechazar justificante          PATCH /tutor/students/{id}/justifications/{jid}
```

#### Módulo: Director de Carrera (career_director)
```
key_name                          Descripción                              Endpoint(s)
────────────────────────────────────────────────────────────────────────────────────────────
career_director.groups.view       Ver grupos y alumnos de su carrera       GET /career-director/groups/*
career_director.teachers.view     Ver profesores de su carrera             GET /career-director/teachers/*
career_director.attendance.view   Ver historial de asistencias             GET /career-director/students/{id}/attendance
career_director.incident.view     Ver incidentes de su carrera             GET /career-director/incidents*
career_director.incident.manage   Crear, editar y cerrar incidentes        POST/PUT/PATCH/POST-close /career-director/incidents/*
career_director.claims.view       Ver reclamaciones dirigidas al director  GET /career-director/claims, GET /career-director/claims/{id}
career_director.claims.action     Ejecutar acción sobre reclamación        PATCH /career-director/claims/{id}/action
career_director.charts.view       Ver gráficas e inteligencia de datos     GET /career-director/charts/*
career_director.devices.view      Ver dispositivos de su carrera           GET /career-director/devices/*
career_director.logs.view         Ver registros de auditoría               GET /career-director/logs/*
```

#### Módulo: Administrador Escolar (administrator — sub-rol por módulo)
```
key_name                          Descripción                              Endpoint(s)
────────────────────────────────────────────────────────────────────────────────────────────
administrator.students.view       Listar y ver detalle de alumnos          GET /administrator/students, GET /administrator/students/{id}
administrator.students.create     Registrar nuevo alumno                   POST /administrator/students
administrator.students.edit       Editar datos de alumno                   PUT /administrator/students/{id}
administrator.students.delete     Desactivar alumno                        DELETE /administrator/students/{id}
administrator.students.tutors     Gestionar tutores familiares del alumno  POST/PUT/DELETE /administrator/students/{id}/tutors/*
administrator.teachers.view       Listar y ver detalle de profesores       GET /administrator/teachers, GET /administrator/teachers/{id}
administrator.teachers.create     Registrar nuevo profesor                 POST /administrator/teachers
administrator.teachers.edit       Editar datos de profesor                 PUT /administrator/teachers/{id}
administrator.teachers.delete     Desactivar profesor                      DELETE /administrator/teachers/{id}
administrator.teachers.tutor      Asignar/quitar tutor académico           PATCH /administrator/teachers/{id}/academic-tutor
administrator.careers.view        Listar y ver carreras                    GET /administrator/careers*
administrator.careers.manage      Crear, editar y desactivar carreras      POST/PUT/DELETE /administrator/careers/*
administrator.school_years.view   Ver ciclos escolares                     GET /administrator/school-years*
administrator.school_years.manage Crear y editar ciclos escolares          POST/PUT /administrator/school-years/*
administrator.subjects.view       Listar y ver materias                    GET /administrator/subjects*
administrator.subjects.manage     Crear, editar y desactivar materias      POST/PUT/DELETE /administrator/subjects/*
administrator.groups.view         Listar y ver grupos                      GET /administrator/groups*
administrator.groups.manage       Crear, editar y desactivar grupos        POST/PUT/DELETE /administrator/groups/*
administrator.devices.view        Listar y ver dispositivos                GET /administrator/devices*
administrator.devices.manage      Registrar, editar y dar de baja          POST/PUT/DELETE /administrator/devices/*
administrator.notifications.view  Ver notificaciones del sistema           GET /administrator/notifications*
administrator.notifications.send  Crear y reenviar notificaciones          POST /administrator/notifications*
administrator.permissions.view    Ver permisos de usuarios                 GET /administrator/users/permissions*
administrator.permissions.manage  Asignar y eliminar overrides             POST/DELETE /administrator/users/{id}/permissions/override*
administrator.full                Permiso especial — acceso total           Todos los anteriores
```

---

### 11.3 Comportamiento de user_permission_overrides

Aplica a **todos los roles** del sistema. Permite ajustes individuales fuera de los permisos heredados del rol o del sub-rol.

```
Tipo ALLOW → Otorga un permiso que el rol/sub-rol del usuario normalmente NO tendría.
Tipo DENY  → Revoca un permiso que el rol/sub-rol del usuario normalmente SÍ tendría.
```

**Ejemplos:**
- Un `profesor` al que se le hace DENY de `profesor.incident.create` → no puede crear incidentes.
- Un `career_director` al que se le hace ALLOW de `career_director.claims.action` adicional si no estaba en su grupo base.
- Un `administrator` con sub-rol `administrator.students` al que se le hace DENY de `administrator.students.delete` → puede ver y crear alumnos pero no desactivarlos.

**Orden de precedencia:**
1. `user_permission_overrides` (ALLOW/DENY individual) — máxima prioridad
2. `permission_groups` del usuario (sub-rol o rol base)
3. Permisos heredados del `role_id`

**Los `career_director` no tienen sub-roles.** Solo pueden recibir overrides individuales sobre los permisos base de su rol.
