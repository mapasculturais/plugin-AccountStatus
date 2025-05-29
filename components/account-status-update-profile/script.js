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
            showButton: true,
            processing: false
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

            this.processing = true;
            api.POST(url, data).then(res => res.json()).then(data => {
                this.processing = false;
                this.showButton = false;
            })
        }
    },
});