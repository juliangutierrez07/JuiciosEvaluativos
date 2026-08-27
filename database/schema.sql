CREATE DATABASE IF NOT EXISTS juicios_evaluativos
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE juicios_evaluativos;

CREATE TABLE programas (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nombre varchar(255) NOT NULL,
  codigo varchar(50) NOT NULL DEFAULT '',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_programas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fichas (
  id int unsigned NOT NULL AUTO_INCREMENT,
  programa_id int unsigned NOT NULL,
  numero varchar(20) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_fichas_numero (numero),
  CONSTRAINT fk_fichas_programa FOREIGN KEY (programa_id)
    REFERENCES programas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aprendices (
  numero_documento varchar(20) NOT NULL,
  ficha_id int unsigned NOT NULL,
  tipo_documento enum('CC','TI') NOT NULL DEFAULT 'CC',
  nombre varchar(255) NOT NULL,
  estado enum('En formación','Retiro Voluntario','Trasladado') NOT NULL DEFAULT 'En formación',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (numero_documento),
  KEY idx_aprendices_ficha (ficha_id),
  KEY idx_aprendices_nombre (nombre),
  CONSTRAINT fk_aprendices_ficha FOREIGN KEY (ficha_id)
    REFERENCES fichas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE competencias (
  id int unsigned NOT NULL AUTO_INCREMENT,
  programa_id int unsigned NOT NULL,
  nombre varchar(500) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_competencias_prog_nombre (programa_id, nombre(191)),
  CONSTRAINT fk_competencias_programa FOREIGN KEY (programa_id)
    REFERENCES programas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resultados_aprendizaje (
  id int unsigned NOT NULL AUTO_INCREMENT,
  competencia_id int unsigned NOT NULL,
  descripcion text NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_ra_comp_desc (competencia_id, descripcion(191)),
  CONSTRAINT fk_ra_competencia FOREIGN KEY (competencia_id)
    REFERENCES competencias (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE juicios_evaluativos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  numero_documento varchar(20) NOT NULL,
  resultado_id int unsigned NOT NULL,
  estado enum('Aprobado','Pendiente') NOT NULL DEFAULT 'Pendiente',
  fecha_juicio datetime DEFAULT NULL,
  funcionario varchar(255) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_juicio_doc_ra (numero_documento, resultado_id),
  KEY idx_juicio_resultado (resultado_id),
  CONSTRAINT fk_juicio_aprendiz FOREIGN KEY (numero_documento)
    REFERENCES aprendices (numero_documento) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_juicio_ra FOREIGN KEY (resultado_id)
    REFERENCES resultados_aprendizaje (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE importaciones (
  id int unsigned NOT NULL AUTO_INCREMENT,
  nombre_archivo varchar(255) NOT NULL,
  programa_id int unsigned NOT NULL,
  ficha_id int unsigned NOT NULL,
  total_filas int unsigned NOT NULL DEFAULT 0,
  filas_procesadas int unsigned NOT NULL DEFAULT 0,
  filas_omitidas int unsigned NOT NULL DEFAULT 0,
  estado enum('Exitoso','Con errores','Fallido') NOT NULL DEFAULT 'Exitoso',
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY fk_import_programa (programa_id),
  KEY fk_import_ficha (ficha_id),
  CONSTRAINT fk_import_ficha FOREIGN KEY (ficha_id)
    REFERENCES fichas (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_import_programa FOREIGN KEY (programa_id)
    REFERENCES programas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

