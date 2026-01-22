app.component('popup-message-update-profile', {
    template: $TEMPLATES['popup-message-update-profile'],

    props: {},

    data() {
        return {
            saving: false,
            agentLoaded: false
        }
    },

    computed: {
        fields() {
            return $MAPAS.config.popupMessageUpdateProfile?.fields ?? [];
        },
        agent() {
            let agentData = $MAPAS.config.popupMessageUpdateProfile?.agentData ;
            agentData.id = $MAPAS.user.profile.id;

            const api = new API("agent");
            let agent = api.getEntityInstance($MAPAS.user.profile.id);
            agent.populate(agentData);

            return agent;
        }
    },

    methods: {
        async saveProfile(modal) {
            if (this.saving) {
                return;
            }
            
            this.saving = true;
            const messages = useMessages();
            
            try {
                // Coleta todos os dados da entidade (não apenas os modificados)
                const dataToSave = this.agent.data(false);
                
                // Usa a API diretamente com forceSave para garantir que salve
                const api = new API('agent');
                const res = await api.PATCH(this.agent.singleUrl, dataToSave, true);
                
                if (res.ok) {
                    const responseData = await res.json();
                    // Atualiza a entidade com a resposta
                    this.agent.populate(responseData);
                    messages.success('Cadastro atualizado com sucesso!');
                    
                    // Fecha o modal após salvar
                    if (modal) {
                        modal.close();
                    }
                } else {
                    const errorData = await res.json();
                    throw new Error(errorData.message || 'Erro ao salvar');
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                messages.error('Erro ao atualizar cadastro. Tente novamente.');
            } finally {
                this.saving = false;
            }
        }
    },
});