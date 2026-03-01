-- SQL para registrar los nuevos proveedores de IA (Mistral, OpenRouter, Hugging Face)
-- Basado en las API Keys probadas en prueba_google.php

INSERT IGNORE INTO ia_proveedores_api (proveedor, api_key, activa, limite_alcanzado_hoy, ultimo_uso) VALUES
('mistral', 'TU_LLAVE_DE_MISTRAL', 1, 0, NOW()),
('openrouter', 'TU_LLAVE_DE_OPENROUTER', 1, 0, NOW()),
('huggingface', 'TU_LLAVE_DE_HUGGINGFACE', 1, 0, NOW());

-- Verificación
SELECT * FROM ia_proveedores_api WHERE proveedor IN ('mistral', 'openrouter', 'huggingface');
