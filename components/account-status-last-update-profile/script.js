app.component('account-status-last-update-profile', {
    template: $TEMPLATES['account-status-last-update-profile'],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
    },

    data() {
        return {}
    },

    computed: {
        lastUpdate() {
            const lastUpdate = $MAPAS.config.accountStatusLastUpdateProfile?.lastUpdate?.date;
            const date = new McDate(lastUpdate);

            return date;
        }
    }
});