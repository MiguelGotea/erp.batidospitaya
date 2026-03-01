-- Plantilla de Registro para Proveedores de IA
-- Tabla: ia_proveedores_api
-- Ubicación sugerida: modulos/gerencia/sql/ia_proovedores_plantilla.sql

-- 1. Google Gemini (Google AI Studio - Muy generoso en Free Tier)
INSERT INTO ia_proveedores_api (proveedor, api_key, cuenta_correo, activa) 
VALUES ('google', 'TU_LLAVE_DE_GOOGLE_STUDIO', 'usuario@gmail.com', 1);

-- 2. Cerebras (LPU Speed - Muy rápido, gratuito actualmente)
INSERT INTO ia_proveedores_api (proveedor, api_key, cuenta_correo, activa) 
VALUES ('cerebras', 'TU_LLAVE_DE_CEREBRAS', 'usuario@gmail.com', 1);

-- 3. OpenAI (ChatGPT)
INSERT INTO ia_proveedores_api (proveedor, api_key, cuenta_correo, activa) 
VALUES ('openai', 'TU_LLAVE_DE_OPENAI', 'usuario@gmail.com', 1);

-- 4. DeepSeek
INSERT INTO ia_proveedores_api (proveedor, api_key, cuenta_correo, activa) 
VALUES ('deepseek', 'TU_LLAVE_DE_DEEPSEEK', 'usuario@gmail.com', 1);

-- 5. Groq (Llama 3 Speed)
INSERT INTO ia_proveedores_api (proveedor, api_key, cuenta_correo, activa) 
VALUES ('groq', 'TU_LLAVE_DE_GROQ', 'usuario@gmail.com', 1);

-- NOTA: Una vez insertadas, el sistema AIService rotará automáticamente entre ellas 
-- si una falla por cuota o error de saldo.
