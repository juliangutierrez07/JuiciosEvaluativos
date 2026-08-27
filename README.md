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
5. Inicia Apache y MySQL y abre `/JuiciosEvaluativos/` en el navegador.

Las credenciales predeterminadas para desarrollo local son `root` sin contraseña. No uses esos valores en producción.

## Despliegue con Docker

El proyecto incluye un `Dockerfile`. Configura `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` en la plataforma y publica el puerto interno `80`. Al iniciar, la aplicación espera a MySQL y crea las tablas que todavía no existan.

## Funciones principales

- Importación de archivos XLSX y XLS.
- Consulta por programa, ficha, estado, documento o aprendiz.
- Detalle de resultados de aprendizaje.
- Reporte consolidado y exportación a Excel.
- Eliminación protegida de fichas y sus registros relacionados.

## Privacidad

El repositorio no incluye la información de aprendices ni los archivos importados. El archivo `database/schema.sql` contiene únicamente la estructura de la base de datos.
