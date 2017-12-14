# Login Bundle

A Symfony ~3.0 Bundle that eases logging users to your Symfony application.

Configuration Sample

```
forci_login:
    managers:
        frontend:
            remember_me: true # If present, will always call remember me services and set a cookie
            firewall_name: frontend_area # Your firewall name
            hwi_oauth: # HWIOAuthBundle integration - for use directly with OAuth Access Tokens
                enabled: true
                token_class: Forci\Bundle\LoginBundle\HWIOAuth\OAuthToken # You may change the token class to this
                # Or to your own class that extends the Bundle's token class. Using the above example 
                # In combination with the below setting will force the Token to return true to isAuthenticated calls
                # This resolves HWIOAuthBundle's issues with serialization and/or your users not having any roles by default
                # Which mostly leads to making HTTP requests to the OAuth APIs on E V E R Y page load.
                # PS You may also use that class, or your own implementation of this idea and a custom 
                # \HWI\Bundle\OAuthBundle\Security\Core\Authentication\Provider\OAuthProvider to prevent that
                # In the case of a normal web-redirect login flow with the bundle
                always_authenticated: true
                user_provider: app.auth.user_provider
```

# TODOs

- Possibly extend UsernamePasswordToken and make it configurable, again with the option to always be considered authenticated?

- Have a good look at those services from Symfony Security and consider implementing calls to those as otherwise redirect target path will not be correctly cleared upon success?

<service id="security.authentication.custom_success_handler" class="Symfony\Component\Security\Http\Authentication\CustomAuthenticationSuccessHandler" abstract="true">
            <argument /> <!-- The custom success handler service id -->
            <argument type="collection" /> <!-- Options -->
            <argument /> <!-- Provider-shared Key -->
        </service>

        <service id="security.authentication.success_handler" class="Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler" abstract="true">
            <argument type="service" id="security.http_utils" />
            <argument type="collection" /> <!-- Options -->
        </service>

        <service id="security.authentication.custom_failure_handler" class="Symfony\Component\Security\Http\Authentication\CustomAuthenticationFailureHandler" abstract="true">
            <argument /> <!-- The custom failure handler service id -->
            <argument type="collection" /> <!-- Options -->
        </service>

        <service id="security.authentication.failure_handler" class="Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler" abstract="true">
            <tag name="monolog.logger" channel="security" />
            <argument type="service" id="http_kernel" />
            <argument type="service" id="security.http_utils" />
            <argument type="collection" /> <!-- Options -->
            <argument type="service" id="logger" on-invalid="null" />
        </service>
