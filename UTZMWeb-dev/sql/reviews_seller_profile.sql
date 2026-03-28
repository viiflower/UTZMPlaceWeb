ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS store_name text;

ALTER TABLE public.users
ADD COLUMN IF NOT EXISTS seller_bio text;

CREATE TABLE IF NOT EXISTS public.order_reviews (
    id bigserial PRIMARY KEY,
    order_id bigint NOT NULL REFERENCES public.orders(id) ON DELETE CASCADE,
    reviewer_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    reviewee_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    rating smallint NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment text,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT order_reviews_unique_reviewer UNIQUE (order_id, reviewer_id)
);

CREATE INDEX IF NOT EXISTS idx_order_reviews_reviewee
ON public.order_reviews (reviewee_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_order_reviews_order
ON public.order_reviews (order_id);
