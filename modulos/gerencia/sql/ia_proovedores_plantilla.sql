-- Plantilla de Registro para Proveedores de IA (Actualizada 2026)
-- Tabla: ia_proveedores_api
-- Ubicación sugerida: modulos/gerencia/sql/ia_proovedores_plantilla.sql

-- 0. Asegurar estructura de la tabla (Columna password añadida en 2026)
ALTER TABLE ia_proveedores_api ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL AFTER api_key;

-- 1. Google Gemini (Google AI Studio - API Nativa v1beta)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('google', 'TU_LLAVE_DE_GOOGLE_STUDIO', 1);

-- 2. Mistral AI (Equilibrado para gráficos)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('mistral', 'TU_LLAVE_DE_MISTRAL', 1);

-- 3. OpenRouter (Multi-modelo / Free Tier)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('openrouter', 'TU_LLAVE_DE_OPENROUTER', 1);

-- 4. Hugging Face (Llama Integración)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('huggingface', 'TU_LLAVE_DE_HF', 1);

-- 5. OpenAI (ChatGPT)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('openai', 'TU_LLAVE_DE_OPENAI', 1);

-- 6. DeepSeek
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('deepseek', 'TU_LLAVE_DE_DEEPSEEK', 1);

-- 7. Cerebras (LPU Speed)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('cerebras', 'TU_LLAVE_DE_CEREBRAS', 1);

-- 8. Groq (Llama 3.3)
INSERT INTO ia_proveedores_api (proveedor, api_key, activa) 
VALUES ('groq', 'TU_LLAVE_DE_GROQ', 1);

-- NOTA: El sistema AIService rotará automáticamente entre estos 8 proveedores
-- priorizando según la cascada definida en ia_graficos_procesar_prompt.php.
