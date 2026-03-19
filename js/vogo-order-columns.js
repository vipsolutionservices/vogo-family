jQuery(document).ready(function () {
  const interval = setInterval(() => {
    if (window.wc && window.wc.wcSettings && window.wc.wcSettings.data) {
      clearInterval(interval);
      const { addFilter } = wp.hooks;
      addFilter(
        "woocommerce_admin_orders_table_row_data",
        "vogo/custom_order_columns",
        (row, order) => {
          row.manual_notes = order.manual_notes || "-";
          row.payment_mode = order.payment_mode || "-";
          row.order_coupon = order.order_coupon || "-";
          row.transport_info = order.transport_info || "-";
          row.order_audit = `<a href="${order.order_audit}" target="_blank">View</a>`;
          return row;
        }
      );
    }
  }, 200);
});
