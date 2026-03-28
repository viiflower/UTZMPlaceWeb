-- Tabla de mensajes entre comprador y vendedor para cada orden
CREATE TABLE IF NOT EXISTS order_messages (
  id SERIAL PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body TEXT NOT NULL,
  attachment_path TEXT,
  attachment_mime TEXT,
  attachment_size INTEGER,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  read_at TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS idx_order_messages_order_created ON order_messages(order_id, created_at);
CREATE INDEX IF NOT EXISTS idx_order_messages_unread ON order_messages(order_id, read_at);
