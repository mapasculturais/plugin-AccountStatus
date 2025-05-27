app.component('account-status-update-profile', {
    template: $TEMPLATES['account-status-update-profile'],

    props: {
        entity: {
            type: Entity,
            required: true,
        },

        fields: {
            type: Array,
            default: []
        },
    },

    data() {
        return {
        }
    },

    computed: {
        verifyData() {
            let disabled = false;

            this.fields.forEach(field => {
                if (this.entity[field] == null || this.entity[field] == '' || this.entity[field] == 'null') {
                    disabled = true;
                }
            });

            return disabled;
        }
    },

    methods: {
        updateProfile() {
            let url = Utils.createUrl('site/atualizar-dados', '');
            let api = new API();
            let data = { 
                agent_id: this.entity.id,
            };

            api.POST(url, data).then(res => res.json()).then(data => {
                // this.entity.save(true);
            })
        }
    },
});