# EvalTrack — Juicios Evaluativos

Aplicación web en PHP para importar, consultar y consolidar juicios evaluativos de aprendices desde archivos Excel.

## Requisitos

- PHP 8.1 o superior con las extensiones PDO MySQL, mbstring, XML, ZIP y GD.
- MySQL o MariaDB.
- Composer.
- Un servidor web como Apache (incluido en XAMPP).

## Instalación local

1. Copia el proyecto dentro de la carpeta pública de tu servidor web.
2. Ejecuta `composer install` en la raíz del proyecto.
3. Crea una base de datos llamada `juicios_evaluativos` e importa `database/schema.sql`.
4. Configura las variables `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` si tus credenciales difieren de las predeterminadas de XAMPP.
5. Crea las tablas nuevas con `php database/migrate.php` (también sirve para actualizar una instalación existente).
6. Crea tu cuenta de administrador: `php database/crear_admin.php <usuario> <contraseña> "Nombre completo"`.
7. Inicia Apache y MySQL y abre `/JuiciosEvaluativos/` en el navegador.

Las credenciales predeterminadas para desarrollo local son `root` sin contraseña. No uses esos valores en producción.

## Despliegue con Docker

El proyecto incluye un `Dockerfile`. Configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` en la plataforma y publica el puerto interno `80`. Al iniciar, la aplicación espera a MySQL y crea las tablas que todavía no existan. Para crear el primer administrador define también `ADMIN_USER`, `ADMIN_PASS` y, opcionalmente, `ADMIN_NAME`; solo se usan si todavía no existe ningún administrador.

## Funciones principales

- Importación de archivos XLSX y XLS.
- Consulta por programa, ficha, estado, documento o aprendiz.
- Detalle de resultados de aprendizaje.
- Reporte consolidado y exportación a Excel.
- Eliminación protegida de fichas y sus registros relacionados.

## Acceso

- **Administradores** ingresan con su usuario y pueden hacer todo: importar, consultar, exportar, eliminar fichas y gestionar usuarios desde el menú **Usuarios**.
- **Instructores** son creados por un administrador. Pueden importar los Excel de juicios, consultar aprendices, reportes y mapa de calor. No pueden eliminar fichas ni gestionar usuarios. En su primer ingreso deben cambiar la contraseña temporal.
- **Aprendices** usan la pestaña *Aprendiz* del login y consultan sus juicios solo con su número de documento, sin contraseña. Ven únicamente su propia información.
- Administradores e instructores usan la pestaña *Instructor / Admin*. Tras 5 contraseñas incorrectas en 15 minutos, la cuenta se bloquea temporalmente.
- Tras 10 consultas con documentos no registrados desde la misma IP en 15 minutos, la consulta de aprendices se bloquea temporalmente para esa IP.

## Privacidad

El repositorio no incluye la información de aprendices ni los archivos importados. El archivo `database/schema.sql` contiene únicamente la estructura de la base de datos.
