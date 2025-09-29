<?php
require_once __DIR__ . '/../config/database.php';

class Chat {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    public function addMessage($ticket_id, $emisor_tipo, $emisor_nombre, $mensaje = null, $foto = null) {
        $sql = "INSERT INTO mtto_chat_mensajes (ticket_id, emisor_tipo, emisor_nombre, mensaje, foto) 
                VALUES (:ticket_id, :emisor_tipo, :emisor_nombre, :mensaje, :foto)";
        
        $params = [
            ':ticket_id' => $ticket_id,
            ':emisor_tipo' => $emisor_tipo,
            ':emisor_nombre' => $emisor_nombre,
            ':mensaje' => $mensaje,
            ':foto' => $foto
        ];
        
        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }
    
    public function getMessages($ticket_id) {
        $sql = "SELECT * FROM mtto_chat_mensajes 
                WHERE ticket_id = :ticket_id 
                ORDER BY created_at ASC";
        
        return $this->db->fetchAll($sql, [':ticket_id' => $ticket_id]);
    }
    
    public function getPinnedMessage($ticket_id) {
        $sql = "SELECT * FROM mtto_chat_mensajes 
                WHERE ticket_id = :ticket_id AND is_pinned = 1 
                ORDER BY created_at DESC LIMIT 1";
        
        return $this->db->fetchOne($sql, [':ticket_id' => $ticket_id]);
    }
    
    public function pinMessage($message_id, $ticket_id) {
        // Primero desmarcar todos los mensajes pinned del ticket
        $sql1 = "UPDATE mtto_chat_mensajes SET is_pinned = 0 WHERE ticket_id = :ticket_id";
        $this->db->query($sql1, [':ticket_id' => $ticket_id]);
        
        // Luego marcar el mensaje específico como pinned
        $sql2 = "UPDATE mtto_chat_mensajes SET is_pinned = 1 WHERE id = :message_id";
        $this->db->query($sql2, [':message_id' => $message_id]);
    }
    
    public function unpinMessage($message_id) {
        $sql = "UPDATE mtto_chat_mensajes SET is_pinned = 0 WHERE id = :message_id";
        $this->db->query($sql, [':message_id' => $message_id]);
    }
    
    public function getUnreadCount($ticket_id, $emisor_tipo) {
        $sql = "SELECT COUNT(*) as count FROM mtto_chat_mensajes 
                WHERE ticket_id = :ticket_id AND emisor_tipo != :emisor_tipo 
                AND created_at > (
                    SELECT COALESCE(MAX(created_at), '1970-01-01') 
                    FROM mtto_chat_mensajes 
                    WHERE ticket_id = :ticket_id AND emisor_tipo = :emisor_tipo
                )";
        
        $result = $this->db->fetchOne($sql, [
            ':ticket_id' => $ticket_id,
            ':emisor_tipo' => $emisor_tipo
        ]);
        
        return $result['count'] ?? 0;
    }
}
?>