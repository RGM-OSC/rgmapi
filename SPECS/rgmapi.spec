Summary:        API for the RGM suite.
Name:           rgmapi
Version:        1.1
Release:        6.rgm
Source0:        %{name}-%{version}.tar.gz
Source1:        rgmapi.conf
Group:          Applications/System
License:        GPL
Vendor:         RGM Community
URL:            https://github.com/EyesOfNetworkCommunity/rgmapi
Requires:       rgmweb
Requires:       php-mcrypt
BuildRequires:  rpm-macros-rgm

BuildRoot:      %{_tmppath}/%{name}-%{version}-root

%define	datadir %{rgm_path}/%{name}

%description
RGM includes a web-based "RESTful" API (Application Programming Interface) called RGMAPI that enables external programs to access information from the monitoring database and to manipulate objects inside the databases of RGM suite.

%prep
%setup -q -n %{name}-%{version}

%build

%install
install -d -m0755 %{buildroot}%{datadir}
cp -afv ./* %{buildroot}%{datadir}
cd %{buildroot}%{datadir}
doxygen %{buildroot}%{datadir}/Doxyfile
install -T -D -m 0644 %{name}-rgm/rgmapi.conf %{buildroot}%{rgm_docdir}/httpd/httpd-rgmapi.example.conf
rm -rf %{buildroot}%{datadir}/%{name}.spec


%post
if [ -e %{_sysconfdir}/httpd/conf.d/%{name}.conf ]; then
    rm -f %{_sysconfdir}/httpd/conf.d/%{name}.conf
fi
systemctl restart httpd


%clean
rm -rf %{buildroot}

%files
%doc %{rgm_docdir}/httpd/httpd-rgmapi.example.conf
%defattr(0644,root,%{rgm_group},0755)
%{rgm_path}


%changelog
* Thu Mar 11 2021 Eric Belhomme <ebelhomme@fr.scc.com> - 1.1.6.rgm
- move httpd config file as example file in /usr/share/doc/rgm/httpd/

* Thu Sep 12 2019 Eric Belhomme <ebelhomme@fr.scc.com> - 1.1-5.rgm
- update Nagios resources routes
- add doxygen API documentation into package

* Thu Apr 25 2019 Michael Aubertin <maubertin@fr.scc.com> - 1.1-4.rgm
- Add functions

* Fri Mar 29 2019 Eric Belhomme <ebelhomme@fr.scc.com> - 1.1-2.rgm
- add rpm-build-rgm as build dependency
- fix group perms

* Mon Mar 04 2019 Michael Aubertin <maubertin@fr.scc.com> - 1.1-1
- Add CI.
- Prepare full fork.

* Wed Nov 28 2018 Michael Aubertin <michael.aubertin@gmail.com> - 1.0-4
- Initialize RGM fork from EON

* Thu Jun 14 2018 Jean-Philippe Levy <jeanphilippe.levy@gmail.com> - 1.0-3
- Add addEventBroker and delEventBroker functions

* Sun May 13 2018 Jean-Philippe Levy <jeanphilippe.levy@gmail.com> - 1.0-2
- Fix installation for EyesOfNetwork 5.2.

* Thu Oct 26 2017 Michael Aubertin <michael.aubertin@gmail.com> - 1.0-1
- Fix permission issue.

* Wed Oct 25 2017 Lucas Salinas - 1.0-0
-Package for EyesOfNetwork API.
