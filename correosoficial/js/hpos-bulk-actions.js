if ( typeof correosoficial_sga_states !== 'undefined' && correosoficial_sga_states.length > 0 ) {
    const sgaActions = {};
    correosoficial_sga_states.forEach(state => {
        sgaActions[state.value] = { label: state.name };
    });

    wp.hooks.addFilter(
        'woocommerce_admin_orders_list_bulk_actions',
        'correosoficial/add-sga-bulk-actions',
        ( actions ) => ({
            ...actions,
            ...sgaActions,
        })
    );
}
