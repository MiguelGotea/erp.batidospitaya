-- SQL Setup for RFM Dashboard
-- Adds indices to optimize RFM and KPI calculations in VentasGlobalesAccessCSV

-- Index for Recency and general date filtering
CREATE INDEX IF NOT EXISTS idx_rfm_fecha ON VentasGlobalesAccessCSV (Fecha);

-- Index for Frequency and Monetary grouping
CREATE INDEX IF NOT EXISTS idx_rfm_cliente_pedido ON VentasGlobalesAccessCSV (CodCliente, CodPedido);

-- Index for filtering by status and type
CREATE INDEX IF NOT EXISTS idx_rfm_anulado_tipo ON VentasGlobalesAccessCSV (Anulado, Tipo);

-- Index for promotion analysis
CREATE INDEX IF NOT EXISTS idx_rfm_promocion ON VentasGlobalesAccessCSV (CodigoPromocion);
