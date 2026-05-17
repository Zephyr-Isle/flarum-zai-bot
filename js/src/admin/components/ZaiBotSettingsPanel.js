import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import m from 'mithril';
import settingsSchema from '../utils/settingsSchema';

export default class ZaiBotSettingsPanel extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.activeTab = 'api';
    this.testingDatabase = false;
    this.databaseTestResult = null;
    this.loadingAdminData = false;
    this.adminDataError = null;
    this.dashboard = null;
    this.providers = [];
    this.agents = [];
    this.providerSaving = false;
    this.agentSaving = false;
    this.maintenanceLoading = false;
    this.maintenanceResult = null;
    this.testingChat = false;
    this.testChatResult = null;
    this.providerForm = this.emptyProviderForm();
    this.agentForm = this.emptyAgentForm();
    this.testChatForm = {
      agentId: '',
      message: '',
    };

    this.loadAdminData();
  }

  translator(key, replacements = {}) {
    return app.translator.trans(`zai-bot.admin.${key}`, replacements, true);
  }

  setting(page, key) {
    return page.setting(`zai-bot.${key}`);
  }

  tabs() {
    return settingsSchema(this.translator.bind(this));
  }

  extensionEnabled(extensionId) {
    const extensions = globalThis.flarum?.extensions || {};

    return Boolean(extensions[extensionId]);
  }

  emptyProviderForm() {
    return {
      id: null,
      name: '',
      driver: 'openai-compatible',
      baseUrl: 'https://api.openai.com/v1',
      apiKey: '',
      modelsText: '[]',
      isActive: true,
    };
  }

  emptyAgentForm() {
    return {
      id: null,
      providerId: '',
      flarumUserId: '',
      name: '',
      username: '',
      email: '',
      personality: 'Helpful AI forum member.',
      expertise: '',
      systemPrompt: '',
      temperature: 0.7,
      isActive: true,
      replyMode: 'mention',
      activeTagsText: '[]',
      chatModel: '',
      embeddingModel: '',
      language: 'zh-CN',
    };
  }

  async apiRequest(path, options = {}) {
    return app.request({
      url: `/api${path}`,
      ...options,
    });
  }

  async loadAdminData() {
    this.loadingAdminData = true;
    this.adminDataError = null;
    m.redraw();

    try {
      const [dashboard, providers, agents] = await Promise.all([
        this.apiRequest('/zai-bot/admin/dashboard'),
        this.apiRequest('/zai-bot/admin/providers'),
        this.apiRequest('/zai-bot/admin/agents'),
      ]);

      this.dashboard = dashboard;
      this.providers = providers.data || [];
      this.agents = agents.data || [];
    } catch (error) {
      this.adminDataError = error?.response?.errors?.[0]?.detail || error?.message || 'Failed to load admin data.';
    } finally {
      this.loadingAdminData = false;
      m.redraw();
    }
  }

  async testDatabase(page) {
    this.testingDatabase = true;
    this.databaseTestResult = null;
    m.redraw();

    try {
      const payload = {
        host: this.setting(page, 'database_host')(),
        port: this.setting(page, 'database_port')(),
        database: this.setting(page, 'database_name')(),
        username: this.setting(page, 'database_username')(),
        password: this.setting(page, 'database_password')(),
      };

      const response = await app.request({
        method: 'POST',
        url: '/api/zai-bot/test-database',
        body: payload,
      });

      this.databaseTestResult = {
        ok: true,
        message: response.message || this.translator('database.connection_success'),
      };
    } catch (error) {
      this.databaseTestResult = {
        ok: false,
        message:
          error?.response?.errors?.[0]?.detail ||
          error?.message ||
          this.translator('database.connection_failed'),
      };
    } finally {
      this.testingDatabase = false;
      m.redraw();
    }
  }

  editProvider(provider) {
    this.providerForm = {
      id: provider.id,
      name: provider.name || '',
      driver: provider.driver || 'openai-compatible',
      baseUrl: provider.baseUrl || 'https://api.openai.com/v1',
      apiKey: '',
      modelsText: JSON.stringify(provider.models || [], null, 2),
      isActive: Boolean(provider.isActive),
    };
  }

  async saveProvider() {
    this.providerSaving = true;
    this.adminDataError = null;
    m.redraw();

    try {
      const models = this.providerForm.modelsText?.trim() ? JSON.parse(this.providerForm.modelsText) : [];
      const body = {
        name: this.providerForm.name,
        driver: this.providerForm.driver,
        baseUrl: this.providerForm.baseUrl,
        apiKey: this.providerForm.apiKey,
        models,
        isActive: this.providerForm.isActive,
      };

      await this.apiRequest(
        this.providerForm.id
          ? `/zai-bot/admin/providers/${this.providerForm.id}`
          : '/zai-bot/admin/providers',
        {
          method: this.providerForm.id ? 'PUT' : 'POST',
          body,
        }
      );

      this.providerForm = this.emptyProviderForm();
      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = error?.response?.errors?.[0]?.detail || error?.message || 'Failed to save provider.';
    } finally {
      this.providerSaving = false;
      m.redraw();
    }
  }

  async deleteProvider(provider) {
    if (!confirm(`Delete provider "${provider.name}"?`)) {
      return;
    }

    try {
      await this.apiRequest(`/zai-bot/admin/providers/${provider.id}`, {
        method: 'DELETE',
      });

      if (this.providerForm.id === provider.id) {
        this.providerForm = this.emptyProviderForm();
      }

      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = error?.response?.errors?.[0]?.detail || error?.message || 'Failed to delete provider.';
      m.redraw();
    }
  }

  editAgent(agent) {
    this.agentForm = {
      id: agent.id,
      providerId: agent.providerId || '',
      flarumUserId: agent.flarumUserId || '',
      name: agent.name || '',
      username: agent.user?.username || '',
      email: agent.user?.email || '',
      personality: agent.personality || '',
      expertise: agent.expertise || '',
      systemPrompt: agent.systemPrompt || '',
      temperature: agent.temperature ?? 0.7,
      isActive: Boolean(agent.isActive),
      replyMode: agent.replyMode || 'mention',
      activeTagsText: JSON.stringify(agent.activeTags || [], null, 2),
      chatModel: agent.chatModel || '',
      embeddingModel: agent.embeddingModel || '',
      language: agent.language || 'zh-CN',
    };
    this.testChatForm.agentId = agent.id;
  }

  async saveAgent() {
    this.agentSaving = true;
    this.adminDataError = null;
    m.redraw();

    try {
      const activeTags = this.agentForm.activeTagsText?.trim() ? JSON.parse(this.agentForm.activeTagsText) : [];
      const body = {
        providerId: this.agentForm.providerId || null,
        flarumUserId: this.agentForm.flarumUserId || null,
        name: this.agentForm.name,
        username: this.agentForm.username,
        email: this.agentForm.email,
        personality: this.agentForm.personality,
        expertise: this.agentForm.expertise,
        systemPrompt: this.agentForm.systemPrompt,
        temperature: Number(this.agentForm.temperature || 0.7),
        isActive: this.agentForm.isActive,
        replyMode: this.agentForm.replyMode,
        activeTags,
        chatModel: this.agentForm.chatModel,
        embeddingModel: this.agentForm.embeddingModel,
        language: this.agentForm.language,
      };

      await this.apiRequest(
        this.agentForm.id
          ? `/zai-bot/admin/agents/${this.agentForm.id}`
          : '/zai-bot/admin/agents',
        {
          method: this.agentForm.id ? 'PUT' : 'POST',
          body,
        }
      );

      this.agentForm = this.emptyAgentForm();
      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = error?.response?.errors?.[0]?.detail || error?.message || 'Failed to save agent.';
    } finally {
      this.agentSaving = false;
      m.redraw();
    }
  }

  async deleteAgent(agent) {
    if (!confirm(`Delete agent "${agent.name}"?`)) {
      return;
    }

    try {
      await this.apiRequest(`/zai-bot/admin/agents/${agent.id}`, {
        method: 'DELETE',
      });

      if (this.agentForm.id === agent.id) {
        this.agentForm = this.emptyAgentForm();
      }

      await this.loadAdminData();
    } catch (error) {
      this.adminDataError = error?.response?.errors?.[0]?.detail || error?.message || 'Failed to delete agent.';
      m.redraw();
    }
  }

  async runMaintenance(action) {
    this.maintenanceLoading = true;
    this.maintenanceResult = null;
    m.redraw();

    try {
      this.maintenanceResult = await this.apiRequest('/zai-bot/admin/maintenance', {
        method: 'POST',
        body: { action },
      });
    } catch (error) {
      this.maintenanceResult = {
        error: error?.response?.errors?.[0]?.detail || error?.message || 'Maintenance request failed.',
      };
    } finally {
      this.maintenanceLoading = false;
      m.redraw();
    }
  }

  async testChat() {
    this.testingChat = true;
    this.testChatResult = null;
    m.redraw();

    try {
      this.testChatResult = await this.apiRequest('/zai-bot/admin/test-chat', {
        method: 'POST',
        body: {
          agentId: this.testChatForm.agentId,
          message: this.testChatForm.message,
        },
      });
    } catch (error) {
      this.testChatResult = {
        error: error?.response?.errors?.[0]?.detail || error?.message || 'Test chat failed.',
      };
    } finally {
      this.testingChat = false;
      m.redraw();
    }
  }

  renderBoolean(page, field) {
    const key = `tabs.${this.activeTab}.fields.${field.key}`;
    const disabled = field.dependency && !this.extensionEnabled(field.dependency);

    return (
      <div className="Form-group">
        <label className="checkbox">
          <input type="checkbox" bidi={this.setting(page, field.key)} disabled={disabled} />
          {this.translator(`${key}.label`)}
        </label>
        <div className="helpText">
          {disabled
            ? this.translator('tools.dependency_missing', { extension: field.dependency })
            : this.translator(`${key}.help`)}
        </div>
      </div>
    );
  }

  renderInput(page, field) {
    const key = `tabs.${this.activeTab}.fields.${field.key}`;

    if (field.type === 'markup') {
      return <div className="ZaiBotSettingsPanel-note">{field.content}</div>;
    }

    if (field.type === 'boolean') {
      return this.renderBoolean(page, field);
    }

    if (field.type === 'textarea') {
      return (
        <div className="Form-group">
          <label>{this.translator(`${key}.label`)}</label>
          <div className="helpText">{this.translator(`${key}.help`)}</div>
          <textarea
            className="FormControl"
            bidi={this.setting(page, field.key)}
            rows={field.rows || 4}
            placeholder={this.translator(`${key}.placeholder`)}
          />
        </div>
      );
    }

    return (
      <div className="Form-group">
        <label>{this.translator(`${key}.label`)}</label>
        <div className="helpText">{this.translator(`${key}.help`)}</div>
        <input
          className="FormControl"
          type={field.type || 'text'}
          bidi={this.setting(page, field.key)}
          placeholder={this.translator(`${key}.placeholder`)}
          min={field.min}
          max={field.max}
          step={field.step}
        />
      </div>
    );
  }

  renderDashboard() {
    if (!this.dashboard?.stats) {
      return null;
    }

    const stats = this.dashboard.stats;

    return (
      <div className="ZaiBotSettingsPanel-metrics">
        {[
          ['Providers', stats.providerCount],
          ['Agents', stats.agentCount],
          ['Active Agents', stats.activeAgentCount],
          ['Memories', stats.memoryCount],
          ['Actions', stats.actionCount],
          ['Failed Actions', stats.failedActionCount],
        ].map(([label, value]) => (
          <div key={label} className="ZaiBotSettingsPanel-metric">
            <div className="ZaiBotSettingsPanel-metricLabel">{label}</div>
            <div className="ZaiBotSettingsPanel-metricValue">{value}</div>
          </div>
        ))}
      </div>
    );
  }

  renderProviderManager() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>Provider CRUD</h4>
          <button type="button" className="Button Button--text" onclick={() => (this.providerForm = this.emptyProviderForm())}>
            New
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-grid">
          <div className="Form-group">
            <label>Name</label>
            <input className="FormControl" value={this.providerForm.name} oninput={(e) => (this.providerForm.name = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Driver</label>
            <input className="FormControl" value={this.providerForm.driver} oninput={(e) => (this.providerForm.driver = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>Base URL</label>
            <input className="FormControl" value={this.providerForm.baseUrl} oninput={(e) => (this.providerForm.baseUrl = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>API Key</label>
            <input className="FormControl" type="password" value={this.providerForm.apiKey} oninput={(e) => (this.providerForm.apiKey = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>Models JSON</label>
            <textarea className="FormControl" rows="5" value={this.providerForm.modelsText} oninput={(e) => (this.providerForm.modelsText = e.target.value)} />
          </div>
          <div className="Form-group">
            <label className="checkbox">
              <input type="checkbox" checked={this.providerForm.isActive} onchange={(e) => (this.providerForm.isActive = e.target.checked)} />
              Active
            </label>
          </div>
        </div>

        <div className="Form-group">
          <button type="button" className="Button Button--primary" disabled={this.providerSaving} onclick={() => this.saveProvider()}>
            {this.providerSaving ? 'Saving...' : this.providerForm.id ? 'Update Provider' : 'Create Provider'}
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-list">
          {this.providers.map((provider) => (
            <div key={provider.id} className="ZaiBotSettingsPanel-listItem">
              <div>
                <strong>{provider.name}</strong>
                <div className="helpText">{provider.driver} · {provider.baseUrl}</div>
              </div>
              <div className="ZaiBotSettingsPanel-actions">
                <button type="button" className="Button Button--text" onclick={() => this.editProvider(provider)}>Edit</button>
                <button type="button" className="Button Button--text" onclick={() => this.deleteProvider(provider)}>Delete</button>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  renderAgentManager() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>Agent CRUD</h4>
          <button type="button" className="Button Button--text" onclick={() => (this.agentForm = this.emptyAgentForm())}>
            New
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-grid">
          <div className="Form-group">
            <label>Name</label>
            <input className="FormControl" value={this.agentForm.name} oninput={(e) => (this.agentForm.name = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Provider</label>
            <select className="FormControl" value={this.agentForm.providerId} onchange={(e) => (this.agentForm.providerId = e.target.value)}>
              <option value="">Default</option>
              {this.providers.map((provider) => (
                <option key={provider.id} value={provider.id}>{provider.name}</option>
              ))}
            </select>
          </div>
          <div className="Form-group">
            <label>Username</label>
            <input className="FormControl" value={this.agentForm.username} oninput={(e) => (this.agentForm.username = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Email</label>
            <input className="FormControl" value={this.agentForm.email} oninput={(e) => (this.agentForm.email = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>Personality</label>
            <textarea className="FormControl" rows="3" value={this.agentForm.personality} oninput={(e) => (this.agentForm.personality = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Expertise</label>
            <input className="FormControl" value={this.agentForm.expertise} oninput={(e) => (this.agentForm.expertise = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Reply Mode</label>
            <select className="FormControl" value={this.agentForm.replyMode} onchange={(e) => (this.agentForm.replyMode = e.target.value)}>
              <option value="mention">mention</option>
              <option value="all">all</option>
            </select>
          </div>
          <div className="Form-group">
            <label>Chat Model</label>
            <input className="FormControl" value={this.agentForm.chatModel} oninput={(e) => (this.agentForm.chatModel = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Embedding Model</label>
            <input className="FormControl" value={this.agentForm.embeddingModel} oninput={(e) => (this.agentForm.embeddingModel = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Temperature</label>
            <input className="FormControl" type="number" min="0" max="1" step="0.1" value={this.agentForm.temperature} oninput={(e) => (this.agentForm.temperature = e.target.value)} />
          </div>
          <div className="Form-group">
            <label>Language</label>
            <input className="FormControl" value={this.agentForm.language} oninput={(e) => (this.agentForm.language = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>System Prompt</label>
            <textarea className="FormControl" rows="4" value={this.agentForm.systemPrompt} oninput={(e) => (this.agentForm.systemPrompt = e.target.value)} />
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>Active Tags JSON</label>
            <textarea className="FormControl" rows="4" value={this.agentForm.activeTagsText} oninput={(e) => (this.agentForm.activeTagsText = e.target.value)} />
          </div>
          <div className="Form-group">
            <label className="checkbox">
              <input type="checkbox" checked={this.agentForm.isActive} onchange={(e) => (this.agentForm.isActive = e.target.checked)} />
              Active
            </label>
          </div>
        </div>

        <div className="Form-group">
          <button type="button" className="Button Button--primary" disabled={this.agentSaving} onclick={() => this.saveAgent()}>
            {this.agentSaving ? 'Saving...' : this.agentForm.id ? 'Update Agent' : 'Create Agent'}
          </button>
        </div>

        <div className="ZaiBotSettingsPanel-list">
          {this.agents.map((agent) => (
            <div key={agent.id} className="ZaiBotSettingsPanel-listItem">
              <div>
                <strong>{agent.name}</strong>
                <div className="helpText">
                  {agent.user?.username || 'no-user'} · {agent.replyMode} · {agent.provider?.name || 'default-provider'}
                </div>
              </div>
              <div className="ZaiBotSettingsPanel-actions">
                <button type="button" className="Button Button--text" onclick={() => this.editAgent(agent)}>Edit</button>
                <button type="button" className="Button Button--text" onclick={() => this.deleteAgent(agent)}>Delete</button>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  renderTestChat() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>Test Chat</h4>
        </div>
        <div className="ZaiBotSettingsPanel-grid">
          <div className="Form-group">
            <label>Agent</label>
            <select className="FormControl" value={this.testChatForm.agentId} onchange={(e) => (this.testChatForm.agentId = e.target.value)}>
              <option value="">Select an agent</option>
              {this.agents.map((agent) => (
                <option key={agent.id} value={agent.id}>{agent.name}</option>
              ))}
            </select>
          </div>
          <div className="Form-group ZaiBotSettingsPanel-gridSpan2">
            <label>Message</label>
            <textarea className="FormControl" rows="4" value={this.testChatForm.message} oninput={(e) => (this.testChatForm.message = e.target.value)} />
          </div>
        </div>
        <div className="Form-group">
          <button type="button" className="Button Button--secondary" disabled={this.testingChat || !this.testChatForm.agentId || !this.testChatForm.message} onclick={() => this.testChat()}>
            {this.testingChat ? 'Testing...' : 'Run Test Chat'}
          </button>
        </div>
        {this.testChatResult && (
          <div className="ZaiBotSettingsPanel-note">
            <pre>{JSON.stringify(this.testChatResult, null, 2)}</pre>
          </div>
        )}
      </div>
    );
  }

  renderMaintenance() {
    return (
      <div className="ZaiBotSettingsPanel-subsection">
        <div className="ZaiBotSettingsPanel-subsectionHeader">
          <h4>Maintenance</h4>
        </div>
        <div className="ZaiBotSettingsPanel-actions">
          {[
            ['export', 'Export State'],
            ['decayMemories', 'Decay Memories'],
            ['cleanupSessions', 'Cleanup Sessions'],
            ['updatePersonalityTags', 'Refresh Personality Tags'],
          ].map(([action, label]) => (
            <button
              key={action}
              type="button"
              className="Button Button--secondary"
              disabled={this.maintenanceLoading}
              onclick={() => this.runMaintenance(action)}
            >
              {label}
            </button>
          ))}
        </div>
        {this.maintenanceResult && (
          <div className="ZaiBotSettingsPanel-note">
            <pre>{JSON.stringify(this.maintenanceResult, null, 2)}</pre>
          </div>
        )}
      </div>
    );
  }

  view(vnode) {
    const page = vnode.attrs.page;
    const tabs = this.tabs();
    const currentTab = tabs.find((tab) => tab.key === this.activeTab) || tabs[0];

    return (
      <div className="ZaiBotSettingsPanel">
        <div className="ZaiBotSettingsPanel-header">
          <h2>{this.translator('page.title')}</h2>
          <p>{this.translator('page.description')}</p>
        </div>

        {this.renderDashboard()}

        {this.adminDataError && <div className="ZaiBotSettingsPanel-note ZaiBotSettingsPanel-status--error">{this.adminDataError}</div>}

        <div className="ZaiBotSettingsPanel-tabs">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              className={`Button ${tab.key === currentTab.key ? 'Button--primary' : 'Button--text'}`}
              onclick={() => {
                this.activeTab = tab.key;
              }}
            >
              {this.translator(`tabs.${tab.key}.title`)}
            </button>
          ))}
        </div>

        <div className="ZaiBotSettingsPanel-section">
          <h3>{this.translator(`tabs.${currentTab.key}.title`)}</h3>
          <p className="helpText">{this.translator(`tabs.${currentTab.key}.description`)}</p>
          {currentTab.fields.map((field, index) => (
            <div key={field.key || `markup-${index}`}>{this.renderInput(page, field)}</div>
          ))}

          {currentTab.key === 'api' && this.renderProviderManager()}
          {currentTab.key === 'agents' && this.renderAgentManager()}
          {currentTab.key === 'agents' && this.renderTestChat()}
          {currentTab.key === 'debug' && this.renderMaintenance()}

          {currentTab.key === 'database' && (
            <div className="Form-group">
              <button
                type="button"
                className="Button Button--secondary"
                disabled={this.testingDatabase}
                onclick={() => this.testDatabase(page)}
              >
                {this.testingDatabase
                  ? this.translator('database.testing')
                  : this.translator('database.test_button')}
              </button>
              {this.databaseTestResult && (
                <div
                  className={`helpText ${
                    this.databaseTestResult.ok
                      ? 'ZaiBotSettingsPanel-status--success'
                      : 'ZaiBotSettingsPanel-status--error'
                  }`}
                >
                  {this.databaseTestResult.message}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    );
  }
}
