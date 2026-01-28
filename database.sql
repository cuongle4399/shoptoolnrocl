-- =========================================================
-- FULL OPTIMIZED SCHEMA for ShopToolNro
-- Bao gồm: Schema gốc + Optimized Functions cho hiệu năng
-- ✅ FIXED: All timestamps stored in UTC (database standard)
-- ✅ Conversion to Asia/Ho_Chi_Minh happens in application layer
-- Copy toàn bộ file này và chạy 1 lần trong Supabase SQL Editor
-- =========================================================
-- IMPORTANT: All now() functions use UTC (Supabase default)
-- Application layer converts to Vietnam time when displaying

-- =========================================================
-- DROP EXISTING OBJECTS
-- =========================================================
DROP TABLE IF EXISTS public.topup_requests CASCADE;
DROP TABLE IF EXISTS public.orders CASCADE;
DROP TABLE IF EXISTS public.infokey CASCADE;
DROP TABLE IF EXISTS public.product_durations CASCADE;
DROP TABLE IF EXISTS public.products CASCADE;
DROP TABLE IF EXISTS public.promotion_codes CASCADE;
DROP TABLE IF EXISTS public.users CASCADE;

DROP FUNCTION IF EXISTS public.prevent_duplicate_pending_orders() CASCADE;
DROP FUNCTION IF EXISTS public.on_topup_approved() CASCADE;
DROP FUNCTION IF EXISTS public.get_product_prices(bigint) CASCADE;
DROP FUNCTION IF EXISTS public.check_license(bigint, text, text, boolean) CASCADE;
DROP FUNCTION IF EXISTS public.on_order_completed() CASCADE;
DROP FUNCTION IF EXISTS public.verify_and_bind_hwid(bigint, bigint, text, text) CASCADE;
DROP FUNCTION IF EXISTS public.get_products_with_durations(int, int) CASCADE;
DROP FUNCTION IF EXISTS public.get_product_detail(bigint) CASCADE;
DROP FUNCTION IF EXISTS public.get_user_orders_detailed(bigint, int, int) CASCADE;
DROP FUNCTION IF EXISTS public.get_topup_requests_detailed(text, int, int) CASCADE;
DROP FUNCTION IF EXISTS public.create_order_atomic(bigint, bigint, bigint, numeric, bigint, text) CASCADE;

DROP VIEW IF EXISTS public.performance_stats CASCADE;

-- =========================================================
-- TABLES
-- =========================================================

-- USERS
CREATE TABLE public.users (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username text NOT NULL UNIQUE,
  email text NOT NULL UNIQUE,
  password_ text NOT NULL,
  balance numeric(15,2) DEFAULT 0,
  role text DEFAULT 'customer',
  status text DEFAULT 'active',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now(),
  reset_token text DEFAULT NULL,
  reset_token_expires_at timestamptz DEFAULT NULL,
  google_id text UNIQUE DEFAULT NULL,
  login_type text DEFAULT 'password',
  avatar_url text DEFAULT NULL
);

-- Insert default accounts (CHANGE THESE CREDENTIALS AFTER DEPLOYMENT!)
INSERT INTO public.users (username, email, password_, balance, role, status)
VALUES 
  ('admin', 'cuong01697072089@gmail.com', '1qaz0plmLQC2k5@@', 0, 'admin', 'active'),
  ('demo_user', 'user@example.com', '1', 0, 'customer', 'active');

-- PRODUCTS
CREATE TABLE public.products (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name text NOT NULL,
  description text,
  image_url text,
  demo_image_url text,
  tutorial_video_url text,
  software_link text,
  category text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now(),
  created_by_admin bigint REFERENCES public.users(id) ON DELETE SET NULL
);

-- PRODUCT DURATIONS
CREATE TABLE public.product_durations (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id bigint NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  duration_days int,
  duration_label text NOT NULL,
  price numeric(10,2) NOT NULL,
  status text DEFAULT 'active',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_product_durations_unique 
  ON public.product_durations(product_id, duration_days) WHERE status = 'active';

-- PROMOTION CODES
CREATE TABLE public.promotion_codes (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code text NOT NULL UNIQUE,
  discount_percent int,
  discount_amount numeric(10,2),
  max_uses int,
  usage_count int DEFAULT 0,
  min_order_amount numeric(10,2),
  expires_at timestamptz,
  created_at timestamptz DEFAULT now()
);

-- LICENSE KEYS (INFOKEY)
CREATE TABLE public.infokey (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  product_id bigint NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  user_id bigint REFERENCES public.users(id) ON DELETE SET NULL,
  hwid text NULL,
  license_key text NOT NULL,
  user_info text NULL,
  status text DEFAULT 'active',
  created_at timestamptz DEFAULT now(),
  assigned_at timestamptz,
  expires_at timestamptz NULL
);

ALTER TABLE public.infokey 
ADD CONSTRAINT infokey_product_license_unique UNIQUE (product_id, license_key);

-- ORDERS
CREATE TABLE public.orders (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  product_id bigint NOT NULL REFERENCES public.products(id) ON DELETE CASCADE,
  product_duration_id bigint NOT NULL REFERENCES public.product_durations(id) ON DELETE RESTRICT,
  infokey_id bigint REFERENCES public.infokey(id) ON DELETE SET NULL,
  total_price numeric(12,2) NOT NULL,
  promotion_code_id bigint REFERENCES public.promotion_codes(id) ON DELETE SET NULL,
  idempotency_key text UNIQUE,
  created_at timestamptz DEFAULT now(),
  completed_at timestamptz
);

-- TOPUP REQUESTS
CREATE TABLE public.topup_requests (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id bigint NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  amount numeric(12,2) NOT NULL,
  description text,
  status text DEFAULT 'pending',
  created_at timestamptz DEFAULT now(),
  approved_at timestamptz,
  approved_by_admin bigint REFERENCES public.users(id) ON DELETE SET NULL,
  rejection_reason text
);

-- =========================================================
-- INDEXES (STANDARD + OPTIMIZED)
-- =========================================================

-- Users
CREATE INDEX IF NOT EXISTS idx_users_username ON public.users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON public.users(email);
CREATE INDEX IF NOT EXISTS idx_users_status ON public.users(status);

-- Products
CREATE INDEX IF NOT EXISTS idx_products_name ON public.products(name);
CREATE INDEX IF NOT EXISTS idx_products_created_at ON public.products(created_at DESC);

-- Product Durations
CREATE INDEX IF NOT EXISTS idx_product_durations_product_id ON public.product_durations(product_id);
CREATE INDEX IF NOT EXISTS idx_product_durations_status ON public.product_durations(status);
CREATE INDEX IF NOT EXISTS idx_product_durations_product_status 
  ON public.product_durations(product_id, status) WHERE status = 'active';

-- Infokey
CREATE INDEX IF NOT EXISTS idx_infokey_license_key ON public.infokey(license_key);
CREATE INDEX IF NOT EXISTS idx_infokey_product_id ON public.infokey(product_id);
CREATE INDEX IF NOT EXISTS idx_infokey_user_id ON public.infokey(user_id);
CREATE INDEX IF NOT EXISTS idx_infokey_hwid ON public.infokey(hwid);
CREATE INDEX IF NOT EXISTS idx_infokey_product_user_status 
  ON public.infokey(product_id, user_id, status) WHERE status = 'active';
CREATE INDEX IF NOT EXISTS idx_infokey_available_licenses 
  ON public.infokey(product_id, status) WHERE user_id IS NULL AND status = 'active';

-- Orders
CREATE INDEX IF NOT EXISTS idx_orders_user_id ON public.orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_infokey_id ON public.orders(infokey_id);
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON public.orders(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_orders_user_created 
  ON public.orders(user_id, created_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_idempotency_key_unique 
  ON public.orders(idempotency_key) WHERE idempotency_key IS NOT NULL;

-- Topup Requests
CREATE INDEX IF NOT EXISTS idx_topup_requests_user_id ON public.topup_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_topup_requests_status ON public.topup_requests(status);
CREATE INDEX IF NOT EXISTS idx_topup_requests_created_at ON public.topup_requests(created_at DESC);

-- Promotion Codes
CREATE INDEX IF NOT EXISTS idx_promotion_codes_code ON public.promotion_codes(code);

-- =========================================================
-- TRIGGERS & BASIC FUNCTIONS
-- =========================================================

-- FUNCTION: prevent duplicate pending orders
CREATE FUNCTION public.prevent_duplicate_pending_orders()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
  existing_id bigint;
BEGIN
  IF NEW.idempotency_key IS NOT NULL THEN
    PERFORM 1 FROM public.orders
    WHERE idempotency_key = NEW.idempotency_key;
    IF FOUND THEN
      RAISE EXCEPTION 'Duplicate order detected via idempotency_key';
    END IF;
  END IF;

  SELECT o.id INTO existing_id FROM public.orders o
  WHERE o.user_id = NEW.user_id
    AND o.product_id = NEW.product_id
    AND o.product_duration_id = NEW.product_duration_id
    AND abs(extract(epoch FROM (now() - o.created_at))) < 300
    AND abs(coalesce(o.total_price, 0) - coalesce(NEW.total_price, 0)) < 0.01
  LIMIT 1;

  IF existing_id IS NOT NULL THEN
    RAISE EXCEPTION 'Duplicate pending order detected (existing id=%).', existing_id;
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_prevent_dup_pending_orders ON public.orders;

CREATE TRIGGER trg_prevent_dup_pending_orders
BEFORE INSERT ON public.orders
FOR EACH ROW
EXECUTE FUNCTION public.prevent_duplicate_pending_orders();

-- FUNCTION: topup approval
CREATE FUNCTION public.on_topup_approved()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF NEW.status = 'approved' AND (OLD.status IS NULL OR OLD.status = 'pending') THEN
    UPDATE public.users
    SET balance = balance + NEW.amount,
        updated_at = now()
    WHERE id = NEW.user_id;
    
    UPDATE public.topup_requests
    SET approved_at = now()
    WHERE id = NEW.id AND approved_at IS NULL;
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_topup_approved ON public.topup_requests;

CREATE TRIGGER trg_topup_approved
AFTER UPDATE ON public.topup_requests
FOR EACH ROW
EXECUTE FUNCTION public.on_topup_approved();

-- FUNCTION: auto assign user to infokey
CREATE FUNCTION public.on_order_completed()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF NEW.completed_at IS NOT NULL AND OLD.completed_at IS NULL THEN
    IF NEW.infokey_id IS NOT NULL THEN
      UPDATE public.infokey
      SET user_id = NEW.user_id,
          assigned_at = now()
      WHERE id = NEW.infokey_id AND user_id IS NULL;
    END IF;
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_order_completed ON public.orders;

CREATE TRIGGER trg_order_completed
AFTER UPDATE ON public.orders
FOR EACH ROW
EXECUTE FUNCTION public.on_order_completed();

-- FUNCTION: get product prices
CREATE FUNCTION public.get_product_prices(p_product_id bigint)
RETURNS TABLE(
  duration_id bigint,
  duration_days int,
  duration_label text,
  price numeric(10,2)
)
LANGUAGE sql
STABLE
AS $$
  SELECT id, duration_days, duration_label, price
  FROM public.product_durations
  WHERE product_id = p_product_id AND status = 'active'
  ORDER BY 
    CASE WHEN duration_days IS NULL THEN 999 ELSE duration_days END,
    price ASC;
$$;

-- FUNCTION: check_license (WITH user_id and username)
-- ✅ Returns expires_at in Asia/Ho_Chi_Minh timezone for Vietnam app
CREATE FUNCTION public.check_license(
  p_product_id bigint,
  p_license_key text,
  p_hwid text,
  p_bind_hwid boolean DEFAULT true
)
RETURNS TABLE(
  valid boolean,
  infokey_id bigint,
  product_id bigint,
  license_key text,
  hwid text,
  status text,
  expires_at timestamptz,
  user_id bigint,
  username text,
  message text
)
LANGUAGE plpgsql
VOLATILE
AS $$
DECLARE
  ik RECORD;
  v_username text;
BEGIN
  IF p_bind_hwid THEN
    SELECT * INTO ik FROM public.infokey
    WHERE infokey.product_id = p_product_id AND infokey.license_key = p_license_key
    LIMIT 1 FOR UPDATE;
  ELSE
    SELECT * INTO ik FROM public.infokey
    WHERE infokey.product_id = p_product_id AND infokey.license_key = p_license_key
    LIMIT 1;
  END IF;

  IF NOT FOUND THEN
    valid := false;
    message := 'Key not found for product';
    RETURN NEXT;
    RETURN;
  END IF;

  -- Get username from users table
  IF ik.user_id IS NOT NULL THEN
    SELECT u.username INTO v_username FROM public.users u WHERE u.id = ik.user_id;
  END IF;

  IF ik.status <> 'active' THEN
    valid := false;
    message := 'Key is banned by Cuong Le';
    infokey_id := ik.id;
    product_id := ik.product_id;
    license_key := ik.license_key;
    hwid := ik.hwid;
    status := ik.status;
    -- ✅ Convert to Vietnam timezone
    expires_at := ik.expires_at AT TIME ZONE 'Asia/Ho_Chi_Minh';
    user_id := ik.user_id;
    username := v_username;
    RETURN NEXT;
    RETURN;
  END IF;

  IF ik.expires_at IS NOT NULL AND ik.expires_at < now() THEN
    valid := false;
    message := 'Key expired';
    infokey_id := ik.id;
    product_id := ik.product_id;
    license_key := ik.license_key;
    hwid := ik.hwid;
    status := ik.status;
    -- ✅ Convert to Vietnam timezone
    expires_at := ik.expires_at AT TIME ZONE 'Asia/Ho_Chi_Minh';
    user_id := ik.user_id;
    username := v_username;
    RETURN NEXT;
    RETURN;
  END IF;

  IF ik.hwid IS NULL THEN
    IF p_hwid IS NOT NULL AND p_bind_hwid THEN
      UPDATE public.infokey
      SET hwid = p_hwid
      WHERE id = ik.id AND hwid IS NULL;
      ik.hwid := p_hwid;
      message := 'Valid and now bound to HWID';
    ELSE
      message := 'Valid (not bound)';
    END IF;
    valid := true;
  ELSE
    IF ik.hwid = p_hwid THEN
      valid := true;
      message := 'Valid and HWID matches';
    ELSE
      valid := false;
      message := 'HWID mismatch';
    END IF;
  END IF;

  infokey_id := ik.id;
  product_id := ik.product_id;
  license_key := ik.license_key;
  hwid := ik.hwid;
  status := ik.status;
  -- ✅ Convert to Vietnam timezone when returning
  expires_at := ik.expires_at AT TIME ZONE 'Asia/Ho_Chi_Minh';
  user_id := ik.user_id;
  username := v_username;
  RETURN NEXT;
END;
$$;

-- FUNCTION: verify_and_bind_hwid
CREATE FUNCTION public.verify_and_bind_hwid(
  p_user_id bigint,
  p_product_id bigint,
  p_license_key text,
  p_hwid text
)
RETURNS TABLE(
  valid boolean,
  product_id bigint,
  license_key text,
  hwid_bound text,
  expires_at timestamptz,
  duration_days int,
  duration_label text,
  username text,
  balance numeric,
  message text
)
LANGUAGE plpgsql
VOLATILE
AS $$
DECLARE
  v_ik RECORD;
  v_order RECORD;
  v_user RECORD;
BEGIN
  SELECT * INTO v_ik FROM public.infokey
  WHERE infokey.license_key = p_license_key 
    AND infokey.user_id = p_user_id 
    AND infokey.product_id = p_product_id
  LIMIT 1 FOR UPDATE;

  IF NOT FOUND THEN
    valid := false;
    message := 'License key not found or does not belong to this user/product';
    product_id := p_product_id;
    license_key := p_license_key;
    RETURN NEXT;
    RETURN;
  END IF;

  IF v_ik.status <> 'active' THEN
    valid := false;
    message := 'License key is not active';
    product_id := v_ik.product_id;
    license_key := v_ik.license_key;
    hwid_bound := v_ik.hwid;
    expires_at := v_ik.expires_at;
    RETURN NEXT;
    RETURN;
  END IF;

  IF v_ik.expires_at IS NOT NULL AND v_ik.expires_at < now() THEN
    valid := false;
    message := 'License key has expired';
    product_id := v_ik.product_id;
    license_key := v_ik.license_key;
    hwid_bound := v_ik.hwid;
    expires_at := v_ik.expires_at;
    RETURN NEXT;
    RETURN;
  END IF;

  IF v_ik.hwid IS NULL THEN
    UPDATE public.infokey
    SET hwid = p_hwid
    WHERE id = v_ik.id;
    
    v_ik.hwid := p_hwid;
    message := 'License activated and bound to HWID';
    valid := true;
  ELSE
    IF v_ik.hwid = p_hwid THEN
      valid := true;
      message := 'License valid, HWID matches';
    ELSE
      valid := false;
      message := 'HWID mismatch - license bound to different device';
      product_id := v_ik.product_id;
      license_key := v_ik.license_key;
      hwid_bound := v_ik.hwid;
      expires_at := v_ik.expires_at;
      RETURN NEXT;
      RETURN;
    END IF;
  END IF;

  SELECT pd.duration_days, pd.duration_label
  INTO v_order
  FROM public.orders o
  JOIN public.product_durations pd ON o.product_duration_id = pd.id
  WHERE o.infokey_id = v_ik.id AND o.completed_at IS NOT NULL
  LIMIT 1;

  SELECT id, username, balance
  INTO v_user
  FROM public.users
  WHERE id = p_user_id;

  product_id := v_ik.product_id;
  license_key := v_ik.license_key;
  hwid_bound := v_ik.hwid;
  expires_at := v_ik.expires_at;
  duration_days := COALESCE(v_order.duration_days, NULL);
  duration_label := COALESCE(v_order.duration_label, 'Permanent');
  username := v_user.username;
  balance := v_user.balance;
  
  RETURN NEXT;
END;
$$;

-- =========================================================
-- OPTIMIZED PERFORMANCE FUNCTIONS
-- =========================================================

-- FUNCTION 1: Get products with durations (REPLACE N+1 QUERIES)
CREATE FUNCTION public.get_products_with_durations(
  p_limit INT DEFAULT 12,
  p_offset INT DEFAULT 0
)
RETURNS JSON
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
  result JSON;
BEGIN
  SELECT json_build_object(
    'products', COALESCE((
      SELECT json_agg(
        json_build_object(
          'id', p.id,
          'name', p.name,
          'description', p.description,
          'image_url', p.image_url,
          'demo_image_url', p.demo_image_url,
          'tutorial_video_url', p.tutorial_video_url,
          'category', p.category,
          'created_at', p.created_at,
          'durations', COALESCE((
            SELECT json_agg(
              json_build_object(
                'id', pd.id,
                'duration_label', pd.duration_label,
                'duration_days', pd.duration_days,
                'price', pd.price,
                'status', pd.status
              )
              ORDER BY 
                CASE WHEN pd.duration_days IS NULL THEN 999999 ELSE pd.duration_days END ASC,
                pd.price ASC
            )
            FROM public.product_durations pd
            WHERE pd.product_id = p.id AND pd.status = 'active'
          ), '[]'::json)
        )
        ORDER BY p.created_at DESC
      )
      FROM (
        SELECT * FROM public.products
        ORDER BY created_at DESC
        LIMIT p_limit
        OFFSET p_offset
      ) p
    ), '[]'::json),
    'total', (SELECT COUNT(*) FROM public.products),
    'limit', p_limit,
    'offset', p_offset
  ) INTO result;
  
  RETURN result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_products_with_durations TO anon, authenticated;

-- FUNCTION 2: Get single product detail with durations
CREATE FUNCTION public.get_product_detail(
  p_product_id BIGINT
)
RETURNS JSON
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
  result JSON;
BEGIN
  SELECT json_build_object(
    'id', p.id,
    'name', p.name,
    'description', p.description,
    'image_url', p.image_url,
    'demo_image_url', p.demo_image_url,
    'tutorial_video_url', p.tutorial_video_url,
    'software_link', p.software_link,
    'category', p.category,
    'created_at', p.created_at,
    'durations', COALESCE((
      SELECT json_agg(
        json_build_object(
          'id', pd.id,
          'duration_label', pd.duration_label,
          'duration_days', pd.duration_days,
          'price', pd.price,
          'status', pd.status
        )
        ORDER BY 
          CASE WHEN pd.duration_days IS NULL THEN 999999 ELSE pd.duration_days END ASC,
          pd.price ASC
      )
      FROM public.product_durations pd
      WHERE pd.product_id = p.id AND pd.status = 'active'
    ), '[]'::json)
  ) INTO result
  FROM public.products p
  WHERE p.id = p_product_id;
  
  RETURN result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_product_detail TO anon, authenticated;

-- FUNCTION 3: Get user orders with product details (OPTIMIZED)
CREATE FUNCTION public.get_user_orders_detailed(
  p_user_id BIGINT,
  p_limit INT DEFAULT 50,
  p_offset INT DEFAULT 0
)
RETURNS JSON
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
  result JSON;
BEGIN
  SELECT json_build_object(
    'orders', COALESCE((
      SELECT json_agg(
        json_build_object(
          'id', o.id,
          'user_id', o.user_id,
          'product_id', o.product_id,
          'product_name', p.name,
          'product_image_url', p.image_url,
          'duration_id', o.product_duration_id,
          'duration_label', pd.duration_label,
          'duration_days', pd.duration_days,
          'total_price', o.total_price,
          'license_key', ik.license_key,
          'hwid', ik.hwid,
          'license_status', ik.status,
          'expires_at', ik.expires_at,
          'created_at', o.created_at,
          'completed_at', o.completed_at
        )
        ORDER BY o.created_at DESC
      )
      FROM (
        SELECT * FROM public.orders
        WHERE user_id = p_user_id
        ORDER BY created_at DESC
        LIMIT p_limit
        OFFSET p_offset
      ) o
      LEFT JOIN public.products p ON o.product_id = p.id
      LEFT JOIN public.product_durations pd ON o.product_duration_id = pd.id
      LEFT JOIN public.infokey ik ON o.infokey_id = ik.id
    ), '[]'::json),
    'total', (SELECT COUNT(*) FROM public.orders WHERE user_id = p_user_id)
  ) INTO result;
  
  RETURN result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_user_orders_detailed TO authenticated;

-- FUNCTION 4: Get topup requests with admin info (ADMIN)
CREATE FUNCTION public.get_topup_requests_detailed(
  p_status TEXT DEFAULT NULL,
  p_limit INT DEFAULT 50,
  p_offset INT DEFAULT 0
)
RETURNS JSON
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
  result JSON;
BEGIN
  SELECT json_build_object(
    'requests', COALESCE((
      SELECT json_agg(
        json_build_object(
          'id', t.id,
          'user_id', t.user_id,
          'username', u.username,
          'email', u.email,
          'amount', t.amount,
          'description', t.description,
          'status', t.status,
          'created_at', t.created_at,
          'approved_at', t.approved_at,
          'approved_by_admin', t.approved_by_admin,
          'admin_username', adm.username,
          'rejection_reason', t.rejection_reason
        )
        ORDER BY t.created_at DESC
      )
      FROM (
        SELECT * FROM public.topup_requests
        WHERE (p_status IS NULL OR status = p_status)
        ORDER BY created_at DESC
        LIMIT p_limit
        OFFSET p_offset
      ) t
      LEFT JOIN public.users u ON t.user_id = u.id
      LEFT JOIN public.users adm ON t.approved_by_admin = adm.id
    ), '[]'::json),
    'total', (SELECT COUNT(*) FROM public.topup_requests WHERE (p_status IS NULL OR status = p_status))
  ) INTO result;
  
  RETURN result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_topup_requests_detailed TO authenticated;

-- FUNCTION 5: Create order with atomic transaction (OPTIMIZED)
CREATE FUNCTION public.create_order_atomic(
  p_user_id BIGINT,
  p_product_id BIGINT,
  p_duration_id BIGINT,
  p_total_price NUMERIC,
  p_promo_code_id BIGINT DEFAULT NULL,
  p_idempotency_key TEXT DEFAULT NULL
)
RETURNS JSON
LANGUAGE plpgsql
VOLATILE
AS $$
DECLARE
  v_user_balance NUMERIC;
  v_license_id BIGINT;
  v_order_id BIGINT;
  v_duration_days INT;
  v_expires_at TIMESTAMPTZ;
  result JSON;
BEGIN
  -- Lock user row để tránh race condition
  SELECT balance INTO v_user_balance
  FROM public.users
  WHERE id = p_user_id
  FOR UPDATE;
  
  IF NOT FOUND THEN
    RETURN json_build_object('success', false, 'message', 'User not found');
  END IF;
  
  -- Check balance
  IF v_user_balance < p_total_price THEN
    RETURN json_build_object('success', false, 'message', 'Insufficient balance');
  END IF;
  
  -- Check duplicate order via idempotency key
  IF p_idempotency_key IS NOT NULL THEN
    SELECT id INTO v_order_id FROM public.orders WHERE idempotency_key = p_idempotency_key;
    IF FOUND THEN
      RETURN json_build_object('success', false, 'message', 'Duplicate order detected');
    END IF;
  END IF;
  
  -- Get available license key
  SELECT id INTO v_license_id
  FROM public.infokey
  WHERE product_id = p_product_id 
    AND user_id IS NULL 
    AND status = 'active'
  LIMIT 1
  FOR UPDATE SKIP LOCKED;
  
  IF NOT FOUND THEN
    RETURN json_build_object('success', false, 'message', 'No license keys available');
  END IF;
  
  -- Get duration info for expiry calculation
  SELECT duration_days INTO v_duration_days
  FROM public.product_durations
  WHERE id = p_duration_id;

  IF v_duration_days IS NOT NULL THEN
    -- ✅ Lưu UTC: Dùng now() thuần túy
    v_expires_at := now() + (v_duration_days || ' days')::interval;
  ELSE
    v_expires_at := NULL; -- Vĩnh viễn
  END IF;
  
  -- Deduct balance
  UPDATE public.users
  SET balance = balance - p_total_price,
      updated_at = now()
  WHERE id = p_user_id;
  
  -- Create order
  INSERT INTO public.orders (
    user_id, product_id, product_duration_id, infokey_id,
    total_price, promotion_code_id, idempotency_key,
    created_at, completed_at
  ) VALUES (
    p_user_id, p_product_id, p_duration_id, v_license_id,
    p_total_price, p_promo_code_id, p_idempotency_key,
    now(), now()
  )
  RETURNING id INTO v_order_id;
  
  -- Assign license to user
  UPDATE public.infokey
  SET user_id = p_user_id,
      assigned_at = now(),
      expires_at = v_expires_at
  WHERE id = v_license_id;
  
  -- Increment promo code usage
  IF p_promo_code_id IS NOT NULL THEN
    UPDATE public.promotion_codes
    SET usage_count = usage_count + 1
    WHERE id = p_promo_code_id;
  END IF;
  
  -- Return success with license key
  SELECT json_build_object(
    'success', true,
    'order_id', v_order_id,
    'license_key', ik.license_key,
    'expires_at', ik.expires_at,
    'message', 'Order created successfully'
  ) INTO result
  FROM public.infokey ik
  WHERE ik.id = v_license_id;
  
  RETURN result;
  
EXCEPTION
  WHEN OTHERS THEN
    RETURN json_build_object('success', false, 'message', SQLERRM);
END;
$$;

GRANT EXECUTE ON FUNCTION public.create_order_atomic TO authenticated;

-- =========================================================
-- PERFORMANCE STATS VIEW (cho monitoring)
-- =========================================================
CREATE OR REPLACE VIEW public.performance_stats AS
SELECT
  'Total Products' as metric,
  COUNT(*)::text as value
FROM public.products
UNION ALL
SELECT
  'Available License Keys',
  COUNT(*)::text
FROM public.infokey
WHERE user_id IS NULL AND status = 'active'
UNION ALL
SELECT
  'Total Orders',
  COUNT(*)::text
FROM public.orders
UNION ALL
SELECT
  'Pending Topups',
  COUNT(*)::text
FROM public.topup_requests
WHERE status = 'pending';

GRANT SELECT ON public.performance_stats TO authenticated;

-- =========================================================
-- EOF - FULL OPTIMIZED SCHEMA
-- ✅ Database stores all timestamps in UTC
-- ✅ Application layer converts to Asia/Ho_Chi_Minh when displaying
-- =========================================================

-- =========================================================
-- SEPAY WEBHOOK INTEGRATION
-- Auto-approve topup requests when bank transfer detected
-- =========================================================

-- Table: sepay_transactions (lưu tất cả giao dịch từ SePay)
CREATE TABLE IF NOT EXISTS public.sepay_transactions (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  gateway text NOT NULL,
  transaction_date timestamptz NOT NULL,
  account_number text,
  sub_account text,
  amount_in numeric(20,2) DEFAULT 0,
  amount_out numeric(20,2) DEFAULT 0,
  accumulated numeric(20,2) DEFAULT 0,
  code text,
  transaction_content text,
  reference_number text,
  description text,
  
  -- Processing status
  processed boolean DEFAULT false,
  matched_topup_id bigint REFERENCES public.topup_requests(id) ON DELETE SET NULL,
  matched_user_id bigint REFERENCES public.users(id) ON DELETE SET NULL,
  
  -- Metadata
  raw_data jsonb,
  created_at timestamptz DEFAULT now(),
  processed_at timestamptz
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_sepay_transactions_date 
  ON public.sepay_transactions(transaction_date DESC);
CREATE INDEX IF NOT EXISTS idx_sepay_transactions_processed 
  ON public.sepay_transactions(processed) WHERE processed = false;
CREATE INDEX IF NOT EXISTS idx_sepay_transactions_reference 
  ON public.sepay_transactions(reference_number);
CREATE INDEX IF NOT EXISTS idx_sepay_transactions_content 
  ON public.sepay_transactions(transaction_content);

-- Table: sepay_webhook_logs (lưu log mỗi lần webhook được gọi)
CREATE TABLE IF NOT EXISTS public.sepay_webhook_logs (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  request_body jsonb,
  request_headers jsonb,
  ip_address text,
  success boolean DEFAULT false,
  error_message text,
  transaction_id bigint REFERENCES public.sepay_transactions(id) ON DELETE SET NULL,
  created_at timestamptz DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_sepay_webhook_logs_created 
  ON public.sepay_webhook_logs(created_at DESC);

-- =========================================================
-- FUNCTION: Auto-process SePay transaction
-- Tự động tìm và duyệt topup request khi có giao dịch khớp
-- =========================================================
CREATE OR REPLACE FUNCTION public.process_sepay_transaction(
  p_transaction_id bigint
)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
  v_transaction RECORD;
  v_topup RECORD;
  v_user_id bigint;
  v_amount numeric;
  v_description text;
  v_result jsonb;
BEGIN
  -- Get transaction info
  SELECT * INTO v_transaction
  FROM public.sepay_transactions
  WHERE id = p_transaction_id AND processed = false;
  
  IF NOT FOUND THEN
    RETURN jsonb_build_object(
      'success', false,
      'message', 'Transaction not found or already processed'
    );
  END IF;
  
  -- Only process incoming transactions
  IF v_transaction.amount_in <= 0 THEN
    RETURN jsonb_build_object(
      'success', false,
      'message', 'Not an incoming transaction'
    );
  END IF;
  
  v_amount := v_transaction.amount_in;
  v_description := LOWER(v_transaction.transaction_content);
  
  -- Try to extract user identifier from transaction content
  -- Format: "shoptoolnro-username-amount" or "shoptoolnro username amount"
  -- Example: "shoptoolnro-admin-50000" or "shoptoolnro admin 50000"
  
  -- Method 1: Find pending topup with matching amount and description
  SELECT * INTO v_topup
  FROM public.topup_requests
  WHERE status = 'pending'
    AND amount = v_amount
    AND LOWER(description) LIKE '%' || v_description || '%'
  ORDER BY created_at ASC
  LIMIT 1;
  
  -- Method 2: Extract username from description
  IF NOT FOUND THEN
    -- Try to match pattern: shoptoolnro-{username}-{amount}
    IF v_description ~ 'shoptoolnro[- ]([a-z0-9]+)[- ]' THEN
      DECLARE
        v_username text;
      BEGIN
        -- Extract username using regex
        v_username := substring(v_description from 'shoptoolnro[- ]([a-z0-9]+)');
        
        -- Find user by username
        SELECT id INTO v_user_id
        FROM public.users
        WHERE LOWER(username) = v_username;
        
        IF FOUND THEN
          -- Find pending topup for this user with matching amount
          SELECT * INTO v_topup
          FROM public.topup_requests
          WHERE user_id = v_user_id
            AND status = 'pending'
            AND amount = v_amount
          ORDER BY created_at ASC
          LIMIT 1;
        END IF;
      END;
    END IF;
  END IF;
  
  -- If found matching topup request, auto-approve it
  IF FOUND THEN
    -- Update user balance
    UPDATE public.users
    SET balance = balance + v_amount,
        updated_at = now()
    WHERE id = v_topup.user_id;
    
    -- Update topup status
    UPDATE public.topup_requests
    SET status = 'approved',
        approved_at = now(),
        approved_by_admin = NULL, -- Auto-approved by system
        description = description || ' [Auto-approved via SePay]'
    WHERE id = v_topup.id;
    
    -- Mark transaction as processed
    UPDATE public.sepay_transactions
    SET processed = true,
        matched_topup_id = v_topup.id,
        matched_user_id = v_topup.user_id,
        processed_at = now()
    WHERE id = p_transaction_id;
    
    v_result := jsonb_build_object(
      'success', true,
      'message', 'Transaction processed and topup approved',
      'topup_id', v_topup.id,
      'user_id', v_topup.user_id,
      'amount', v_amount
    );
  ELSE
    -- No matching topup found, just mark as processed
    UPDATE public.sepay_transactions
    SET processed = true,
        processed_at = now()
    WHERE id = p_transaction_id;
    
    v_result := jsonb_build_object(
      'success', false,
      'message', 'No matching topup request found',
      'amount', v_amount,
      'description', v_transaction.transaction_content
    );
  END IF;
  
  RETURN v_result;
END;
$$;

GRANT EXECUTE ON FUNCTION public.process_sepay_transaction TO authenticated, anon;

-- =========================================================
-- TRIGGER: Auto-process new SePay transactions
-- =========================================================
CREATE OR REPLACE FUNCTION public.on_sepay_transaction_insert()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
BEGIN
  -- Only auto-process if it's an incoming transaction
  IF NEW.amount_in > 0 THEN
    PERFORM public.process_sepay_transaction(NEW.id);
  END IF;
  
  RETURN NEW;
END;
$$;

-- Drop trigger if exists to allow re-running this script
DROP TRIGGER IF EXISTS trg_sepay_transaction_insert ON public.sepay_transactions;

CREATE TRIGGER trg_sepay_transaction_insert
AFTER INSERT ON public.sepay_transactions
FOR EACH ROW
EXECUTE FUNCTION public.on_sepay_transaction_insert();

-- =========================================================
-- Grant permissions
-- =========================================================
GRANT SELECT, INSERT ON public.sepay_transactions TO anon, authenticated;
GRANT SELECT, INSERT ON public.sepay_webhook_logs TO anon, authenticated;

-- =========================================================
-- END OF SEPAY INTEGRATION
-- =========================================================
