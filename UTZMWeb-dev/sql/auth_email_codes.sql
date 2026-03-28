ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS email_verified boolean;

UPDATE public.users
SET email_verified = true
WHERE email_verified IS NULL;

ALTER TABLE public.users
ALTER COLUMN email_verified SET DEFAULT false;

ALTER TABLE public.users
ALTER COLUMN email_verified SET NOT NULL;

CREATE TABLE IF NOT EXISTS public.email_codes (
    id bigserial PRIMARY KEY,
    user_id bigint NULL REFERENCES public.users(id) ON DELETE CASCADE,
    email varchar(255) NOT NULL,
    purpose varchar(40) NOT NULL,
    code_hash varchar(64) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    used_at timestamp without time zone NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_email_codes_lookup
ON public.email_codes (email, purpose, used_at, expires_at);
