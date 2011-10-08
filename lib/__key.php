<?php
  /* User-Key & Secret
  -----------------------------------------------------------------------------------------*/
  if (WP_RW__LOCALHOST)
  {
      // Development User-Key
      define('WP_RW__USER_KEY', 'e3af9e7cd16379e1cadb7f3a31b2601b');
  }
  else
  {
      // Production User-Key
      define('WP_RW__USER_KEY', 'e3b1e16e330ab6158e133e7a5ada1844');
  }
  define('WP_RW__USER_SECRET', '43ce224bdf26792a7064b1ca77859a56');
?>