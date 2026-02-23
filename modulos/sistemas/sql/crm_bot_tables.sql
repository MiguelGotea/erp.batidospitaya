-- ============================================================
-- crm_bot_tables.sql
-- Tablas para el CRM Bot Híbrido WhatsApp
-- Ejecutar en la BD pitaya_erp
-- ============================================================

-- Tabla de conversaciones (una por instancia + número cliente)
CREATE TABLE IF NOT EXISTS conversations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    instancia           VARCHAR(30)  NOT NULL COMMENT 'wsp-clientes, wsp-crmbot...',
    numero_cliente      VARCHAR(20)  NOT NULL COMMENT 'Número del cliente que escribe',
    numero_remitente    VARCHAR(20)  NOT NULL COMMENT 'Número actual en wsp_sesion_vps_ al momento',
    status              ENUM('bot','humano') NOT NULL DEFAULT 'bot',
    last_intent         VARCHAR(100) DEFAULT NULL COMMENT 'Última intención detectada para contexto',
    last_interaction_at DATETIME     DEFAULT NULL,
    created_at          DATETIME     NOT NULL DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00')),
    updated_at          DATETIME     NOT NULL DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00'))
                        ON UPDATE (CONVERT_TZ(NOW(),'+00:00','-06:00')),
    UNIQUE KEY uq_conv (instancia, numero_cliente),
    INDEX idx_instancia (instancia),
    INDEX idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de mensajes (historial unificado: user, bot, agent, campaign)
CREATE TABLE IF NOT EXISTS messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT          NOT NULL,
    direction       ENUM('in','out') NOT NULL,
    sender_type     ENUM('user','bot','agent','campaign') NOT NULL,
    message_text    TEXT         DEFAULT NULL,
    message_type    VARCHAR(20)  NOT NULL DEFAULT 'text',
    enviado_ok      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00')),
    INDEX idx_conversation (conversation_id),
    INDEX idx_created_at   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de intenciones del bot (configurables desde ERP)
CREATE TABLE IF NOT EXISTS bot_intents (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    intent_name         VARCHAR(100) NOT NULL COMMENT 'Ej: saludo, precio, horario, queja',
    keywords            TEXT         DEFAULT NULL COMMENT 'Palabras clave separadas por coma',
    response_templates  JSON         NOT NULL     COMMENT 'Array de strings: variantes de respuesta',
    priority            INT          NOT NULL DEFAULT 1 COMMENT 'Mayor = más prioridad',
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    instancia           VARCHAR(30)  DEFAULT NULL COMMENT 'NULL = aplica a todas las instancias',
    created_at          DATETIME     NOT NULL DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00')),
    updated_at          DATETIME     NOT NULL DEFAULT (CONVERT_TZ(NOW(),'+00:00','-06:00'))
                        ON UPDATE (CONVERT_TZ(NOW(),'+00:00','-06:00'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de vectores TF-IDF precalculados para similitud coseno (Nivel 3 embeddings)
CREATE TABLE IF NOT EXISTS intent_embeddings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    intent_id       INT          NOT NULL,
    term            VARCHAR(100) NOT NULL COMMENT 'Término del vocabulario',
    tfidf_weight    FLOAT        NOT NULL DEFAULT 0 COMMENT 'Peso TF-IDF normalizado',
    INDEX idx_intent_id (intent_id),
    INDEX idx_term      (term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intenciones iniciales de ejemplo
INSERT INTO bot_intents (intent_name, keywords, response_templates, priority) VALUES
('saludo',
 'hola,buenos días,buenas tardes,buenas noches,hey,buenas,buen dia',
 '["¡Hola {{nombre}}! 😊 ¿En qué te puedo ayudar hoy?","¡Buenas {{nombre}}! 🎉 Soy el asistente de Batidos Pitaya. ¿Cómo te puedo ayudar?","¡Hola {{nombre}}! Bienvenido a Batidos Pitaya 🍓. ¿Qué necesitas?"]',
 10),
('horario',
 'horario,hora,abierto,cierran,abren,atienden,cuándo,cuando,disponible',
 '["¡Estamos abiertos de lunes a domingo de 8am a 9pm! ⏰🍓","Nuestro horario es de 8:00am a 9:00pm todos los días 😊🕗","Atendemos de 8am a 9pm, ¡todos los días! No descansamos 😄🍹"]',
 5),
('ubicacion',
 'donde,dónde,dirección,direccion,ubicación,ubicacion,sucursal,tienda,local',
 '["Puedes encontrar nuestras sucursales en el sitio web pitaya.com 📍","¡Tenemos varias sucursales! Consulta todas en pitaya.com/sucursales 🗺️"]',
 5),
('humano',
 'asesor,humano,agente,persona,soporte,ayuda real,hablar con alguien',
 '["Entendido {{nombre}}, te voy a conectar con un asesor. ¡Un momento por favor! 👨‍💼","Claro {{nombre}}, te transferiré con nuestro equipo de atención. ¡Ya te atienden! 🙌"]',
 20),
('no_entiendo',
 '',
 '["No entendí muy bien tu mensaje 😅. ¿Puedes contarme más?","Hmm, no estoy seguro de entenderte 🤔. ¿Puedes reformular tu pregunta?","Disculpa {{nombre}}, no entendí. ¿Quieres hablar con un asesor?"]',
 0);
