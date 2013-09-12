SELECT
  ps.id_product,
  ps.id_category_default,

  m.name AS manufacturer,

  IF(p.ean13, p.ean13, p.upc) AS product_code,
  p.supplier_reference,

  pl.name AS title,
  pl.description,
  pl.description_short,
  pl.meta_title,
  pl.meta_keywords,
  pl.meta_description,

  cl.link_rewrite,

  im.id_image
FROM
  (ps_product p
  INNER JOIN ps_product_shop ps
    ON p.id_product = ps.id_product AND ps.id_shop = 1)
  LEFT JOIN ps_product_lang pl
    ON p.id_product = pl.id_product AND pl.id_shop = 1 AND pl.id_lang = 1
  LEFT JOIN ps_manufacturer m
    ON p.id_manufacturer = m.id_manufacturer
  LEFT JOIN ps_category_lang cl
    ON p.id_category_default = cl.id_category AND cl.id_shop = 1 AND cl.id_lang = 1
  LEFT JOIN (ps_image im INNER JOIN ps_image_shop ims ON im.id_image = ims.id_image)
    ON p.id_product = im.id_product AND ims.id_shop = 1 AND ims.cover = 1
ORDER BY
  p.id_product;

-----

SELECT
  c.id_category,
  c.id_parent,
  c.level_depth,
  c.nleft,
  c.nright
FROM
  ps_category c
  INNER JOIN ps_category_product cp
    ON (c.id_category = cp.id_category AND cp.id_product = 1)
  INNER JOIN ps_category_shop cs
    ON (c.id_category = cs.id_category AND cs.id_shop = 1)
WHERE
  c.active = 1
ORDER BY
  c.nleft DESC,
  c.nright ASC;

-----

SELECT
  cl.name
FROM
  ps_category_lang cl INNER JOIN ps_category parent
    ON (parent.id_category = cl.id_category),
  ps_category node
WHERE
  node.nleft BETWEEN parent.nleft AND parent.nright
  AND node.id_category = 14
  AND cl.id_shop = 2
  AND cl.id_lang = 1
  AND parent.level_depth <> 0
  AND parent.active = 1
ORDER BY parent.nleft;
