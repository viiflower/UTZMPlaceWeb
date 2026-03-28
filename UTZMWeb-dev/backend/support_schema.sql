BEGIN;

ALTER TABLE public.users
  ADD COLUMN IF NOT EXISTS role text NOT NULL DEFAULT 'customer';

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'users_role_check'
  ) THEN
    ALTER TABLE public.users
      ADD CONSTRAINT users_role_check
      CHECK (role IN ('customer', 'support', 'admin'));
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.support_tickets (
  id bigserial PRIMARY KEY,
  user_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  assigned_to bigint REFERENCES public.users(id) ON DELETE SET NULL,
  order_id bigint REFERENCES public.orders(id) ON DELETE SET NULL,
  category text NOT NULL,
  subject text NOT NULL,
  description text NOT NULL,
  status text NOT NULL DEFAULT 'open',
  priority text NOT NULL DEFAULT 'normal',
  created_at timestamp without time zone NOT NULL DEFAULT now(),
  updated_at timestamp without time zone NOT NULL DEFAULT now(),
  last_message_at timestamp without time zone NOT NULL DEFAULT now(),
  CONSTRAINT support_tickets_status_check
    CHECK (status IN ('open', 'in_progress', 'waiting_user', 'resolved', 'closed')),
  CONSTRAINT support_tickets_priority_check
    CHECK (priority IN ('low', 'normal', 'high', 'urgent'))
);

CREATE TABLE IF NOT EXISTS public.support_ticket_messages (
  id bigserial PRIMARY KEY,
  ticket_id bigint NOT NULL REFERENCES public.support_tickets(id) ON DELETE CASCADE,
  sender_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  body text NOT NULL,
  is_internal boolean NOT NULL DEFAULT false,
  created_at timestamp without time zone NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS support_tickets_user_id_idx
  ON public.support_tickets (user_id);

CREATE INDEX IF NOT EXISTS support_tickets_assigned_to_idx
  ON public.support_tickets (assigned_to);

CREATE INDEX IF NOT EXISTS support_tickets_status_idx
  ON public.support_tickets (status);

CREATE INDEX IF NOT EXISTS support_tickets_last_message_at_idx
  ON public.support_tickets (last_message_at DESC);

CREATE INDEX IF NOT EXISTS support_ticket_messages_ticket_id_idx
  ON public.support_ticket_messages (ticket_id, created_at);

COMMIT;
